<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organization;
use App\Models\WorkoutLog;
use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Leaderboard Controller
 * Handles global and organization leaderboards with rankings
 */
class LeaderboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get Global Leaderboard
     * GET /api/leaderboard/global
     */
    public function getGlobalLeaderboard(Request $request)
    {
        try {
            $metric = $request->input('metric', 'points'); // points, workouts, streak, achievements
            $period = $request->input('period', 'all_time'); // all_time, month, week
            $limit = $request->input('limit', 100);
            $offset = $request->input('offset', 0);

            $cacheKey = "leaderboard_global_{$metric}_{$period}_{$limit}_{$offset}";
            $cacheDuration = 300; // 5 minutes

            $leaderboard = Cache::remember($cacheKey, $cacheDuration, function () use ($metric, $period, $limit, $offset) {
                return $this->buildLeaderboard('global', null, $metric, $period, $limit, $offset);
            });

            return response()->json([
                'success' => true,
                'data' => $leaderboard,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load global leaderboard',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Organization Leaderboard
     * GET /api/leaderboard/organization/{id}
     */
    public function getOrganizationLeaderboard(Request $request, $organizationId)
    {
        try {
            $metric = $request->input('metric', 'points');
            $period = $request->input('period', 'all_time');
            $limit = $request->input('limit', 100);
            $offset = $request->input('offset', 0);

            // Verify organization exists
            $organization = Organization::find($organizationId);
            if (!$organization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organization not found',
                ], 404);
            }

            $cacheKey = "leaderboard_org_{$organizationId}_{$metric}_{$period}_{$limit}_{$offset}";
            $cacheDuration = 300; // 5 minutes

            $leaderboard = Cache::remember($cacheKey, $cacheDuration, function () use ($organizationId, $metric, $period, $limit, $offset) {
                return $this->buildLeaderboard('organization', $organizationId, $metric, $period, $limit, $offset);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'organization' => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                    ],
                    'leaderboard' => $leaderboard,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load organization leaderboard',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get User Rank
     * GET /api/leaderboard/user/{id}/rank
     */
    public function getUserRank(Request $request, $userId)
    {
        try {
            $metric = $request->input('metric', 'points');
            $period = $request->input('period', 'all_time');
            $scope = $request->input('scope', 'global'); // global or organization

            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $rank = $this->calculateUserRank($userId, $metric, $period, $scope, $user->organization_id);

            $nearbyUsers = $this->getNearbyUsers($userId, $metric, $period, $scope, $user->organization_id, $rank);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'avatar' => $user->profile_photo_url ?? null,
                    ],
                    'rank' => $rank,
                    'metric' => $metric,
                    'period' => $period,
                    'scope' => $scope,
                    'value' => $this->getUserMetricValue($user, $metric, $period),
                    'nearbyUsers' => $nearbyUsers,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user rank',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Friends Leaderboard
     * GET /api/leaderboard/friends
     */
    public function getFriendsLeaderboard(Request $request)
    {
        try {
            $userId = Auth::id();
            $metric = $request->input('metric', 'points');
            $period = $request->input('period', 'all_time');

            $friendIds = $this->getFriendIds($userId);
            $friendIds[] = $userId; // Include self

            $leaderboard = $this->buildFriendsLeaderboard($friendIds, $metric, $period);

            return response()->json([
                'success' => true,
                'data' => $leaderboard,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load friends leaderboard',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Helper Methods

    protected function buildLeaderboard($scope, $scopeId, $metric, $period, $limit, $offset)
    {
        $query = User::query();

        // Apply scope filter
        if ($scope === 'organization' && $scopeId) {
            $query->where('organization_id', $scopeId);
        }

        // Get users with metrics
        $users = $query->where('status', 'active')
            ->select('id', 'name', 'email', 'profile_photo_url', 'organization_id')
            ->get()
            ->map(function ($user) use ($metric, $period) {
                $value = $this->getUserMetricValue($user, $metric, $period);
                return [
                    'user' => $user,
                    'value' => $value,
                ];
            })
            ->filter(function ($item) {
                return $item['value'] > 0; // Only show users with non-zero values
            })
            ->sortByDesc('value')
            ->values();

        // Paginate
        $paginatedUsers = $users->slice($offset, $limit);

        // Format output
        return [
            'metric' => $metric,
            'period' => $period,
            'rankings' => $paginatedUsers->map(function ($item, $index) use ($offset) {
                $rank = $offset + $index + 1;
                return [
                    'rank' => $rank,
                    'user' => [
                        'id' => $item['user']->id,
                        'name' => $item['user']->name,
                        'avatar' => $item['user']->profile_photo_url ?? null,
                    ],
                    'value' => $item['value'],
                    'badge' => $this->getRankBadge($rank),
                ];
            })->values(),
            'totalUsers' => $users->count(),
        ];
    }

    protected function buildFriendsLeaderboard($friendIds, $metric, $period)
    {
        $users = User::whereIn('id', $friendIds)
            ->where('status', 'active')
            ->get()
            ->map(function ($user) use ($metric, $period) {
                $value = $this->getUserMetricValue($user, $metric, $period);
                return [
                    'user' => $user,
                    'value' => $value,
                ];
            })
            ->filter(function ($item) {
                return $item['value'] > 0;
            })
            ->sortByDesc('value')
            ->values();

        return [
            'metric' => $metric,
            'period' => $period,
            'rankings' => $users->map(function ($item, $index) {
                $rank = $index + 1;
                return [
                    'rank' => $rank,
                    'user' => [
                        'id' => $item['user']->id,
                        'name' => $item['user']->name,
                        'avatar' => $item['user']->profile_photo_url ?? null,
                    ],
                    'value' => $item['value'],
                    'badge' => $this->getRankBadge($rank),
                ];
            })->values(),
            'totalUsers' => $users->count(),
        ];
    }

    protected function getUserMetricValue($user, $metric, $period)
    {
        [$startDate, $endDate] = $this->getDateRange($period);

        return match($metric) {
            'points' => $this->getUserPoints($user->id, $startDate, $endDate),
            'workouts' => $this->getUserWorkouts($user->id, $startDate, $endDate),
            'streak' => $this->getUserStreak($user->id),
            'achievements' => $this->getUserAchievements($user->id, $startDate, $endDate),
            'active_minutes' => $this->getUserActiveMinutes($user->id, $startDate, $endDate),
            'calories_burned' => $this->getUserCaloriesBurned($user->id, $startDate, $endDate),
            default => 0,
        };
    }

    protected function getUserPoints($userId, $startDate, $endDate)
    {
        if ($startDate && $endDate) {
            // Period-specific points (from activity logs or points table)
            return DB::table('gamification_points')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('points') ?? 0;
        }

        // All-time points from user table
        return User::find($userId)->total_points ?? 0;
    }

    protected function getUserWorkouts($userId, $startDate, $endDate)
    {
        $query = WorkoutLog::where('user_id', $userId)
            ->where('completed', true);

        if ($startDate && $endDate) {
            $query->whereBetween('completed_at', [$startDate, $endDate]);
        }

        return $query->count();
    }

    protected function getUserStreak($userId)
    {
        // Current streak from user table or calculate
        $user = User::find($userId);
        return $user->current_streak ?? 0;
    }

    protected function getUserAchievements($userId, $startDate, $endDate)
    {
        $query = DB::table('user_achievements')
            ->where('user_id', $userId);

        if ($startDate && $endDate) {
            $query->whereBetween('earned_at', [$startDate, $endDate]);
        }

        return $query->count();
    }

    protected function getUserActiveMinutes($userId, $startDate, $endDate)
    {
        $query = WorkoutLog::where('user_id', $userId)
            ->where('completed', true);

        if ($startDate && $endDate) {
            $query->whereBetween('completed_at', [$startDate, $endDate]);
        }

        return $query->sum('duration_minutes') ?? 0;
    }

    protected function getUserCaloriesBurned($userId, $startDate, $endDate)
    {
        $query = WorkoutLog::where('user_id', $userId)
            ->where('completed', true);

        if ($startDate && $endDate) {
            $query->whereBetween('completed_at', [$startDate, $endDate]);
        }

        return $query->sum('calories_burned') ?? 0;
    }

    protected function calculateUserRank($userId, $metric, $period, $scope, $organizationId)
    {
        $query = User::query();

        if ($scope === 'organization' && $organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $users = $query->where('status', 'active')
            ->get()
            ->map(function ($user) use ($metric, $period) {
                return [
                    'id' => $user->id,
                    'value' => $this->getUserMetricValue($user, $metric, $period),
                ];
            })
            ->filter(function ($item) {
                return $item['value'] > 0;
            })
            ->sortByDesc('value')
            ->values();

        $rank = $users->search(function ($item) use ($userId) {
            return $item['id'] === $userId;
        });

        return $rank !== false ? $rank + 1 : null;
    }

    protected function getNearbyUsers($userId, $metric, $period, $scope, $organizationId, $rank, $range = 2)
    {
        if (!$rank) {
            return [];
        }

        $query = User::query();

        if ($scope === 'organization' && $organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $users = $query->where('status', 'active')
            ->get()
            ->map(function ($user) use ($metric, $period) {
                return [
                    'user' => $user,
                    'value' => $this->getUserMetricValue($user, $metric, $period),
                ];
            })
            ->filter(function ($item) {
                return $item['value'] > 0;
            })
            ->sortByDesc('value')
            ->values();

        $startIndex = max(0, $rank - $range - 1);
        $endIndex = min($users->count(), $rank + $range);

        return $users->slice($startIndex, $endIndex - $startIndex)
            ->map(function ($item, $index) use ($startIndex) {
                $itemRank = $startIndex + $index + 1;
                return [
                    'rank' => $itemRank,
                    'user' => [
                        'id' => $item['user']->id,
                        'name' => $item['user']->name,
                        'avatar' => $item['user']->profile_photo_url ?? null,
                    ],
                    'value' => $item['value'],
                ];
            })
            ->values();
    }

    protected function getFriendIds($userId)
    {
        return DB::table('friendships')
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('friend_id', $userId);
            })
            ->where('status', 'accepted')
            ->get()
            ->map(function ($friendship) use ($userId) {
                return $friendship->user_id === $userId ? $friendship->friend_id : $friendship->user_id;
            })
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getDateRange($period)
    {
        return match($period) {
            'week' => [now()->startOfWeek(), now()],
            'month' => [now()->startOfMonth(), now()],
            'quarter' => [now()->startOfQuarter(), now()],
            'year' => [now()->startOfYear(), now()],
            'all_time' => [null, null],
            default => [null, null],
        };
    }

    protected function getRankBadge($rank)
    {
        return match(true) {
            $rank === 1 => '🥇',
            $rank === 2 => '🥈',
            $rank === 3 => '🥉',
            $rank <= 10 => '⭐',
            default => null,
        };
    }
}
