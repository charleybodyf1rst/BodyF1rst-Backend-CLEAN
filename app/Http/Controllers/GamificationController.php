<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GamificationController extends Controller
{
    /**
     * Get user's current streaks (workout, nutrition, spirit & mindset, etc.)
     */
    public function getStreaks(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Calculate workout streak
            $workoutStreak = $this->calculateWorkoutStreak($user->id);

            // Calculate nutrition streak
            $nutritionStreak = $this->calculateNutritionStreak($user->id);

            // Calculate overall/combined streak
            $overallStreak = $this->calculateOverallStreak($user->id);

            // Get longest streaks (all-time records)
            $longestStreaks = DB::table('user_streak_records')
                ->where('user_id', $user->id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'current' => [
                        'workout' => $workoutStreak,
                        'nutrition' => $nutritionStreak,
                        'overall' => $overallStreak
                    ],
                    'longest' => [
                        'workout' => $longestStreaks->longest_workout_streak ?? 0,
                        'nutrition' => $longestStreaks->longest_nutrition_streak ?? 0,
                        'overall' => $longestStreaks->longest_overall_streak ?? 0
                    ],
                    'last_activity' => [
                        'workout' => $this->getLastActivityDate($user->id, 'workout'),
                        'nutrition' => $this->getLastActivityDate($user->id, 'nutrition')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching streaks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch streaks',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's achievements and badges
     */
    public function getAchievements(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Get unlocked achievements
            $unlockedAchievements = DB::table('user_achievements')
                ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
                ->where('user_achievements.user_id', $user->id)
                ->select(
                    'achievements.*',
                    'user_achievements.unlocked_at',
                    'user_achievements.progress'
                )
                ->orderBy('user_achievements.unlocked_at', 'desc')
                ->get();

            // Get available achievements (not yet unlocked)
            $availableAchievements = DB::table('achievements')
                ->whereNotIn('id', function ($query) use ($user) {
                    $query->select('achievement_id')
                        ->from('user_achievements')
                        ->where('user_id', $user->id);
                })
                ->where('is_active', true)
                ->get();

            // Get achievement progress for available achievements
            foreach ($availableAchievements as $achievement) {
                $achievement->progress = $this->calculateAchievementProgress($user->id, $achievement);
            }

            // Get badges
            $badges = DB::table('user_badges')
                ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
                ->where('user_badges.user_id', $user->id)
                ->select('badges.*', 'user_badges.earned_at')
                ->orderBy('user_badges.earned_at', 'desc')
                ->get();

            // Get achievement categories stats
            $stats = [
                'total_unlocked' => $unlockedAchievements->count(),
                'total_available' => $availableAchievements->count() + $unlockedAchievements->count(),
                'total_badges' => $badges->count(),
                'body_points_earned' => $unlockedAchievements->sum('points_reward'),
                'completion_percentage' => $this->calculateCompletionPercentage($user->id)
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'unlocked' => $unlockedAchievements,
                    'available' => $availableAchievements,
                    'badges' => $badges,
                    'stats' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching achievements', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch achievements',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update streak when user completes an activity
     */
    public function updateStreak(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $activityType = $request->input('type'); // workout, nutrition
            $date = $request->input('date', now()->toDateString());

            // Record the activity
            DB::table('user_activity_log')->insert([
                'user_id' => $user->id,
                'activity_type' => $activityType,
                'activity_date' => $date,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Recalculate streaks
            $streaks = [
                'workout' => $this->calculateWorkoutStreak($user->id),
                'nutrition' => $this->calculateNutritionStreak($user->id),
                'overall' => $this->calculateOverallStreak($user->id)
            ];

            // Update longest streak records if necessary
            $this->updateLongestStreaks($user->id, $streaks);

            // Check for streak-based achievements
            $this->checkStreakAchievements($user->id, $streaks);

            return response()->json([
                'success' => true,
                'message' => 'Streak updated successfully',
                'data' => [
                    'current_streaks' => $streaks
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating streak', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update streak',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate workout streak
     */
    private function calculateWorkoutStreak(int $userId): int
    {
        $logs = DB::table('workout_logs')
            ->where('user_id', $userId)
            ->where('completed', true)
            ->orderBy('completed_at', 'desc')
            ->pluck('completed_at')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->unique()
            ->values()
            ->toArray();

        return $this->calculateStreakFromDates($logs);
    }

    /**
     * Calculate nutrition streak
     */
    private function calculateNutritionStreak(int $userId): int
    {
        $logs = DB::table('meal_logs')
            ->where('user_id', $userId)
            ->orderBy('meal_time', 'desc')
            ->pluck('meal_time')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->unique()
            ->values()
            ->toArray();

        return $this->calculateStreakFromDates($logs);
    }

    /**
     * Calculate overall streak (any activity)
     */
    private function calculateOverallStreak(int $userId): int
    {
        // Combine all activity dates
        $workoutDates = DB::table('workout_logs')
            ->where('user_id', $userId)
            ->where('completed', true)
            ->pluck('completed_at')
            ->map(fn($d) => Carbon::parse($d)->toDateString());

        $nutritionDates = DB::table('meal_logs')
            ->where('user_id', $userId)
            ->pluck('meal_time')
            ->map(fn($d) => Carbon::parse($d)->toDateString());

        $allDates = $workoutDates->merge($nutritionDates)
            ->unique()
            ->sort()
            ->values()
            ->reverse()
            ->toArray();

        return $this->calculateStreakFromDates($allDates);
    }

    /**
     * Calculate streak from array of dates (must be sorted desc)
     */
    private function calculateStreakFromDates(array $dates): int
    {
        if (empty($dates)) {
            return 0;
        }

        $streak = 0;
        $currentDate = Carbon::now()->startOfDay();

        foreach ($dates as $date) {
            $activityDate = Carbon::parse($date)->startOfDay();

            if ($activityDate->eq($currentDate)) {
                $streak++;
                $currentDate->subDay();
            } elseif ($activityDate->lt($currentDate)) {
                // Gap in streak
                break;
            }
        }

        return $streak;
    }

    /**
     * Get last activity date for a specific type
     */
    private function getLastActivityDate(int $userId, string $type): ?string
    {
        switch ($type) {
            case 'workout':
                $date = DB::table('workout_logs')
                    ->where('user_id', $userId)
                    ->where('completed', true)
                    ->max('completed_at');
                break;
            case 'nutrition':
                $date = DB::table('meal_logs')
                    ->where('user_id', $userId)
                    ->max('meal_time');
                break;
            default:
                return null;
        }

        return $date ? Carbon::parse($date)->toDateTimeString() : null;
    }

    /**
     * Update longest streak records
     */
    private function updateLongestStreaks(int $userId, array $currentStreaks): void
    {
        $record = DB::table('user_streak_records')->where('user_id', $userId)->first();

        $data = [
            'user_id' => $userId,
            'longest_workout_streak' => max($record->longest_workout_streak ?? 0, $currentStreaks['workout']),
            'longest_nutrition_streak' => max($record->longest_nutrition_streak ?? 0, $currentStreaks['nutrition']),
            'longest_overall_streak' => max($record->longest_overall_streak ?? 0, $currentStreaks['overall']),
            'updated_at' => now()
        ];

        DB::table('user_streak_records')->updateOrInsert(
            ['user_id' => $userId],
            $data
        );
    }

    /**
     * Calculate achievement progress
     */
    private function calculateAchievementProgress(int $userId, object $achievement): array
    {
        // This would calculate based on achievement criteria
        // For now, return a simple structure
        return [
            'current' => 0,
            'required' => 100,
            'percentage' => 0
        ];
    }

    /**
     * Calculate overall completion percentage
     */
    private function calculateCompletionPercentage(int $userId): int
    {
        $totalAchievements = DB::table('achievements')->where('is_active', true)->count();
        $unlockedAchievements = DB::table('user_achievements')->where('user_id', $userId)->count();

        if ($totalAchievements === 0) {
            return 0;
        }

        return (int) round(($unlockedAchievements / $totalAchievements) * 100);
    }

    /**
     * Check and unlock streak-based achievements
     */
    private function checkStreakAchievements(int $userId, array $streaks): void
    {
        // Check workout streaks
        $this->checkAndUnlockAchievement($userId, 'workout_streak_7', $streaks['workout'] >= 7);
        $this->checkAndUnlockAchievement($userId, 'workout_streak_30', $streaks['workout'] >= 30);
        $this->checkAndUnlockAchievement($userId, 'workout_streak_100', $streaks['workout'] >= 100);

        // Check nutrition streaks
        $this->checkAndUnlockAchievement($userId, 'nutrition_streak_7', $streaks['nutrition'] >= 7);
        $this->checkAndUnlockAchievement($userId, 'nutrition_streak_30', $streaks['nutrition'] >= 30);

        // Check overall streaks
        $this->checkAndUnlockAchievement($userId, 'overall_streak_7', $streaks['overall'] >= 7);
        $this->checkAndUnlockAchievement($userId, 'overall_streak_30', $streaks['overall'] >= 30);
    }

    /**
     * Check and unlock a specific achievement
     */
    private function checkAndUnlockAchievement(int $userId, string $achievementCode, bool $condition): void
    {
        if (!$condition) {
            return;
        }

        $achievement = DB::table('achievements')->where('code', $achievementCode)->first();

        if (!$achievement) {
            return;
        }

        // Check if already unlocked
        $exists = DB::table('user_achievements')
            ->where('user_id', $userId)
            ->where('achievement_id', $achievement->id)
            ->exists();

        if (!$exists) {
            // Unlock achievement
            DB::table('user_achievements')->insert([
                'user_id' => $userId,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
                'created_at' => now()
            ]);

            // Award body points
            if ($achievement->points_reward > 0) {
                DB::table('users')
                    ->where('id', $userId)
                    ->increment('body_points', $achievement->points_reward);
            }

            Log::info('Achievement unlocked', [
                'user_id' => $userId,
                'achievement' => $achievementCode,
                'points' => $achievement->points_reward
            ]);
        }
    }

    /**
     * Get global leaderboard (top users by body points)
     */
    public function getLeaderboard(Request $request): JsonResponse
    {
        try {
            $limit = $request->query('limit', 100);
            $organizationId = $request->query('organization_id');
            $userId = Auth::id();

            $query = DB::table('users')
                ->select(
                    'id',
                    'name',
                    'avatar',
                    'body_points',
                    DB::raw('RANK() OVER (ORDER BY body_points DESC) as rank')
                )
                ->where('body_points', '>', 0)
                ->orderBy('body_points', 'desc')
                ->limit($limit);

            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }

            $leaderboard = $query->get();

            // Get current user's rank
            $userRank = DB::table('users')
                ->where('body_points', '>',
                    DB::table('users')->where('id', $userId)->value('body_points')
                )
                ->count() + 1;

            $userPoints = DB::table('users')->where('id', $userId)->value('body_points');

            return response()->json([
                'success' => true,
                'data' => [
                    'leaderboard' => $leaderboard,
                    'user_rank' => $userRank,
                    'user_points' => $userPoints,
                    'total_users' => DB::table('users')->where('body_points', '>', 0)->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching leaderboard', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leaderboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's badges
     */
    public function getBadges(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Get earned badges
            $earnedBadges = DB::table('user_badges')
                ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
                ->where('user_badges.user_id', $user->id)
                ->select(
                    'badges.*',
                    'user_badges.earned_at'
                )
                ->orderBy('user_badges.earned_at', 'desc')
                ->get();

            // Get available badges (not yet earned)
            $availableBadges = DB::table('badges')
                ->whereNotIn('id', function ($query) use ($user) {
                    $query->select('badge_id')
                        ->from('user_badges')
                        ->where('user_id', $user->id);
                })
                ->where('is_active', true)
                ->get();

            // Group by tier
            $badgesByTier = [
                'bronze' => $earnedBadges->where('tier', 'bronze'),
                'silver' => $earnedBadges->where('tier', 'silver'),
                'gold' => $earnedBadges->where('tier', 'gold'),
                'platinum' => $earnedBadges->where('tier', 'platinum'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'earned' => $earnedBadges,
                    'available' => $availableBadges,
                    'by_tier' => $badgesByTier,
                    'stats' => [
                        'total_earned' => $earnedBadges->count(),
                        'total_available' => $availableBadges->count() + $earnedBadges->count(),
                        'bronze_count' => $badgesByTier['bronze']->count(),
                        'silver_count' => $badgesByTier['silver']->count(),
                        'gold_count' => $badgesByTier['gold']->count(),
                        'platinum_count' => $badgesByTier['platinum']->count(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching badges', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch badges',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get points transaction history
     */
    public function getPointsHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = $request->query('limit', 50);

            $transactions = DB::table('body_points_transactions')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $stats = [
                'total_points' => DB::table('users')->where('id', $user->id)->value('body_points'),
                'total_transactions' => DB::table('body_points_transactions')->where('user_id', $user->id)->count(),
                'points_this_week' => DB::table('body_points_transactions')
                    ->where('user_id', $user->id)
                    ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()])
                    ->sum('points'),
                'points_this_month' => DB::table('body_points_transactions')
                    ->where('user_id', $user->id)
                    ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()])
                    ->sum('points'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'stats' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching points history', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch points history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get gamification dashboard (overview)
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Get streaks
            $streaks = [
                'workout' => $this->calculateWorkoutStreak($user->id),
                'nutrition' => $this->calculateNutritionStreak($user->id),
                'overall' => $this->calculateOverallStreak($user->id)
            ];

            // Get recent achievements
            $recentAchievements = DB::table('user_achievements')
                ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
                ->where('user_achievements.user_id', $user->id)
                ->select('achievements.*', 'user_achievements.unlocked_at')
                ->orderBy('user_achievements.unlocked_at', 'desc')
                ->limit(5)
                ->get();

            // Get recent badges
            $recentBadges = DB::table('user_badges')
                ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
                ->where('user_badges.user_id', $user->id)
                ->select('badges.*', 'user_badges.earned_at')
                ->orderBy('user_badges.earned_at', 'desc')
                ->limit(5)
                ->get();

            // Get user rank
            $userRank = DB::table('users')
                ->where('body_points', '>', $user->body_points)
                ->count() + 1;

            // Get total users with points
            $totalUsers = DB::table('users')->where('body_points', '>', 0)->count();

            // Points this week
            $pointsThisWeek = DB::table('body_points_transactions')
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()])
                ->sum('points');

            return response()->json([
                'success' => true,
                'data' => [
                    'body_points' => $user->body_points,
                    'rank' => $userRank,
                    'total_users' => $totalUsers,
                    'points_this_week' => $pointsThisWeek,
                    'streaks' => $streaks,
                    'recent_achievements' => $recentAchievements,
                    'recent_badges' => $recentBadges,
                    'achievement_count' => DB::table('user_achievements')->where('user_id', $user->id)->count(),
                    'badge_count' => DB::table('user_badges')->where('user_id', $user->id)->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching gamification dashboard', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
