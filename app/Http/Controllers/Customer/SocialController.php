<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SocialController extends Controller
{
    // ========================================================================
    // FRIENDS MANAGEMENT
    // ========================================================================

    /**
     * Get friends list
     * GET /api/get-friends
     */
    public function getFriends(Request $request)
    {
        try {
            $userId = auth()->id();

            $friends = DB::table('friendships')
                ->where(function($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhere('friend_id', $userId);
                })
                ->where('status', 'accepted')
                ->get()
                ->map(function($friendship) use ($userId) {
                    $friendId = $friendship->user_id == $userId ? $friendship->friend_id : $friendship->user_id;
                    $friend = DB::table('users')->find($friendId);
                    return [
                        'id' => $friend->id,
                        'name' => $friend->name,
                        'email' => $friend->email,
                        'profile_picture' => $friend->profile_picture ?? null,
                        'friendship_since' => $friendship->created_at
                    ];
                });

            return response()->json(['success' => true, 'data' => $friends]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    /**
     * Send friend request
     * POST /api/send-friend-request
     */
    public function sendFriendRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'friend_id' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();
            $friendId = $request->friend_id;

            if ($userId == $friendId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot send friend request to yourself'
                ], 400);
            }

            // Check if friendship already exists
            $exists = DB::table('friendships')
                ->where(function($query) use ($userId, $friendId) {
                    $query->where('user_id', $userId)->where('friend_id', $friendId);
                })
                ->orWhere(function($query) use ($userId, $friendId) {
                    $query->where('user_id', $friendId)->where('friend_id', $userId);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friend request already exists'
                ], 400);
            }

            DB::table('friendships')->insert([
                'user_id' => $userId,
                'friend_id' => $friendId,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Friend request sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending friend request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept friend request
     * POST /api/accept-friend-request/{id}
     */
    public function acceptFriendRequest($id)
    {
        try {
            $userId = auth()->id();

            $friendship = DB::table('friendships')
                ->where('id', $id)
                ->where('friend_id', $userId)
                ->where('status', 'pending')
                ->first();

            if (!$friendship) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friend request not found'
                ], 404);
            }

            DB::table('friendships')
                ->where('id', $id)
                ->update([
                    'status' => 'accepted',
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Friend request accepted'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting friend request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject friend request
     * POST /api/reject-friend-request/{id}
     */
    public function rejectFriendRequest($id)
    {
        try {
            $userId = auth()->id();

            $friendship = DB::table('friendships')
                ->where('id', $id)
                ->where('friend_id', $userId)
                ->where('status', 'pending')
                ->first();

            if (!$friendship) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friend request not found'
                ], 404);
            }

            DB::table('friendships')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Friend request rejected'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting friend request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove friend
     * DELETE /api/remove-friend/{id}
     */
    public function removeFriend($id)
    {
        try {
            $userId = auth()->id();

            $deleted = DB::table('friendships')
                ->where(function($query) use ($userId, $id) {
                    $query->where('user_id', $userId)->where('friend_id', $id);
                })
                ->orWhere(function($query) use ($userId, $id) {
                    $query->where('user_id', $id)->where('friend_id', $userId);
                })
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friendship not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Friend removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing friend',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get friend requests
     * GET /api/get-friend-requests
     */
    public function getFriendRequests(Request $request)
    {
        try {
            $userId = auth()->id();

            $requests = DB::table('friendships')
                ->where('friend_id', $userId)
                ->where('status', 'pending')
                ->get()
                ->map(function($friendship) {
                    $user = DB::table('users')->find($friendship->user_id);
                    return [
                        'id' => $friendship->id,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'profile_picture' => $user->profile_picture ?? null,
                        'requested_at' => $friendship->created_at
                    ];
                });

            return response()->json(['success' => true, 'data' => $requests]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    /**
     * Get friend suggestions
     * GET /api/get-friend-suggestions
     */
    public function getFriendSuggestions(Request $request)
    {
        try {
            $userId = auth()->id();
            $limit = $request->get('limit', 10);

            // Get current friend IDs
            $friendIds = DB::table('friendships')
                ->where(function($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhere('friend_id', $userId);
                })
                ->pluck('friend_id')
                ->merge(DB::table('friendships')
                    ->where(function($query) use ($userId) {
                        $query->where('user_id', $userId)
                              ->orWhere('friend_id', $userId);
                    })
                    ->pluck('user_id'))
                ->unique()
                ->toArray();

            $friendIds[] = $userId;

            // Get suggestions (users not already friends)
            $suggestions = DB::table('users')
                ->whereNotIn('id', $friendIds)
                ->where('role', 'customer')
                ->limit($limit)
                ->get()
                ->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'profile_picture' => $user->profile_picture ?? null,
                        'mutual_friends' => 0 // TODO: Calculate mutual friends
                    ];
                });

            return response()->json(['success' => true, 'data' => $suggestions]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    // ========================================================================
    // ACTIVITY FEED
    // ========================================================================

    /**
     * Get activity feed
     * GET /api/get-activity-feed
     */
    public function getActivityFeed(Request $request)
    {
        try {
            $userId = auth()->id();
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);

            // Get friend IDs
            $friendIds = DB::table('friendships')
                ->where(function($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhere('friend_id', $userId);
                })
                ->where('status', 'accepted')
                ->pluck('friend_id')
                ->merge(DB::table('friendships')
                    ->where(function($query) use ($userId) {
                        $query->where('user_id', $userId)
                              ->orWhere('friend_id', $userId);
                    })
                    ->where('status', 'accepted')
                    ->pluck('user_id'))
                ->unique()
                ->toArray();

            $friendIds[] = $userId;

            $activities = DB::table('social_activities')
                ->whereIn('user_id', $friendIds)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $activitiesWithUser = collect($activities->items())->map(function($activity) {
                $user = DB::table('users')->find($activity->user_id);
                return [
                    'id' => $activity->id,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'profile_picture' => $user->profile_picture ?? null
                    ],
                    'type' => $activity->type,
                    'content' => $activity->content,
                    'data' => json_decode($activity->data ?? '{}'),
                    'likes_count' => $activity->likes_count ?? 0,
                    'comments_count' => $activity->comments_count ?? 0,
                    'created_at' => $activity->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $activitiesWithUser,
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'total_pages' => $activities->lastPage(),
                    'total_items' => $activities->total(),
                    'per_page' => $activities->perPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_items' => 0,
                    'per_page' => 20
                ]
            ]);
        }
    }

    /**
     * Post activity
     * POST /api/post-activity
     */
    public function postActivity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:50',
            'content' => 'required|string',
            'data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $activityId = DB::table('social_activities')->insertGetId([
                'user_id' => auth()->id(),
                'type' => $request->type,
                'content' => $request->content,
                'data' => json_encode($request->data ?? []),
                'likes_count' => 0,
                'comments_count' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Activity posted successfully',
                'data' => ['id' => $activityId]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error posting activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Like activity
     * POST /api/like-activity/{id}
     */
    public function likeActivity($id)
    {
        try {
            $userId = auth()->id();

            $activity = DB::table('social_activities')->find($id);
            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found'
                ], 404);
            }

            // Check if already liked
            $liked = DB::table('activity_likes')
                ->where('activity_id', $id)
                ->where('user_id', $userId)
                ->exists();

            if ($liked) {
                // Unlike
                DB::table('activity_likes')
                    ->where('activity_id', $id)
                    ->where('user_id', $userId)
                    ->delete();

                DB::table('social_activities')
                    ->where('id', $id)
                    ->decrement('likes_count');

                return response()->json([
                    'success' => true,
                    'message' => 'Activity unliked',
                    'liked' => false
                ]);
            } else {
                // Like
                DB::table('activity_likes')->insert([
                    'activity_id' => $id,
                    'user_id' => $userId,
                    'created_at' => now()
                ]);

                DB::table('social_activities')
                    ->where('id', $id)
                    ->increment('likes_count');

                return response()->json([
                    'success' => true,
                    'message' => 'Activity liked',
                    'liked' => true
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error liking activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Comment on activity
     * POST /api/comment-activity/{id}
     */
    public function commentActivity($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $activity = DB::table('social_activities')->find($id);
            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found'
                ], 404);
            }

            $commentId = DB::table('activity_comments')->insertGetId([
                'activity_id' => $id,
                'user_id' => auth()->id(),
                'comment' => $request->comment,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::table('social_activities')
                ->where('id', $id)
                ->increment('comments_count');

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'data' => ['id' => $commentId]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete activity
     * DELETE /api/delete-activity/{id}
     */
    public function deleteActivity($id)
    {
        try {
            $userId = auth()->id();

            $activity = DB::table('social_activities')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found or unauthorized'
                ], 404);
            }

            DB::table('social_activities')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Activity deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========================================================================
    // USER PROFILES
    // ========================================================================

    /**
     * Get user profile
     * GET /api/get-user-profile/{id}
     */
    public function getUserProfile($id)
    {
        try {
            $user = DB::table('users')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $profile = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_picture' => $user->profile_picture ?? null,
                'bio' => $user->bio ?? null,
                'joined_at' => $user->created_at,
                'stats' => [
                    'total_workouts' => 0, // TODO: Calculate from workouts table
                    'total_points' => 0,
                    'achievements_count' => 0
                ]
            ];

            return response()->json(['success' => true, 'data' => $profile]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user stats
     * GET /api/get-user-stats/{id}
     */
    public function getUserStats($id)
    {
        try {
            $stats = [
                'total_workouts' => 0,
                'total_calories_burned' => 0,
                'total_distance_km' => 0,
                'total_duration_minutes' => 0,
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_points' => 0,
                'level' => 1,
                'achievements_count' => 0
            ];

            // TODO: Calculate actual stats from database

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => [
                'total_workouts' => 0,
                'total_calories_burned' => 0,
                'total_distance_km' => 0,
                'total_duration_minutes' => 0,
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_points' => 0,
                'level' => 1,
                'achievements_count' => 0
            ]]);
        }
    }

    /**
     * Get user achievements
     * GET /api/get-user-achievements/{id}
     */
    public function getUserAchievements($id)
    {
        try {
            $achievements = DB::table('user_achievements')
                ->where('user_id', $id)
                ->get()
                ->map(function($achievement) {
                    $achievementData = DB::table('achievements')->find($achievement->achievement_id);
                    return [
                        'id' => $achievement->id,
                        'achievement_id' => $achievement->achievement_id,
                        'name' => $achievementData->name ?? 'Unknown',
                        'description' => $achievementData->description ?? '',
                        'icon' => $achievementData->icon ?? null,
                        'earned_at' => $achievement->earned_at
                    ];
                });

            return response()->json(['success' => true, 'data' => $achievements]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    // ========================================================================
    // LEADERBOARD
    // ========================================================================

    /**
     * Get leaderboard
     * GET /api/get-leaderboard
     */
    public function getLeaderboard(Request $request)
    {
        try {
            $period = $request->get('period', 'all_time'); // all_time, month, week
            $limit = $request->get('limit', 100);

            $leaderboard = DB::table('users')
                ->select('id', 'name', 'profile_picture', 'total_points')
                ->where('role', 'customer')
                ->orderBy('total_points', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($user, $index) {
                    return [
                        'rank' => $index + 1,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'profile_picture' => $user->profile_picture ?? null,
                        'points' => $user->total_points ?? 0
                    ];
                });

            return response()->json(['success' => true, 'data' => $leaderboard]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    /**
     * Get friends leaderboard
     * GET /api/get-friends-leaderboard
     */
    public function getFriendsLeaderboard(Request $request)
    {
        try {
            $userId = auth()->id();
            $limit = $request->get('limit', 50);

            // Get friend IDs
            $friendIds = DB::table('friendships')
                ->where(function($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhere('friend_id', $userId);
                })
                ->where('status', 'accepted')
                ->pluck('friend_id')
                ->merge(DB::table('friendships')
                    ->where(function($query) use ($userId) {
                        $query->where('user_id', $userId)
                              ->orWhere('friend_id', $userId);
                    })
                    ->where('status', 'accepted')
                    ->pluck('user_id'))
                ->unique()
                ->toArray();

            $friendIds[] = $userId;

            $leaderboard = DB::table('users')
                ->select('id', 'name', 'profile_picture', 'total_points')
                ->whereIn('id', $friendIds)
                ->orderBy('total_points', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($user, $index) {
                    return [
                        'rank' => $index + 1,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'profile_picture' => $user->profile_picture ?? null,
                        'points' => $user->total_points ?? 0
                    ];
                });

            return response()->json(['success' => true, 'data' => $leaderboard]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    /**
     * Get organization leaderboard
     * GET /api/get-organization-leaderboard/{organizationId}
     */
    public function getOrganizationLeaderboard($organizationId, Request $request)
    {
        try {
            $limit = $request->get('limit', 100);

            $leaderboard = DB::table('users')
                ->select('id', 'name', 'profile_picture', 'total_points')
                ->where('organization_id', $organizationId)
                ->where('role', 'customer')
                ->orderBy('total_points', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($user, $index) {
                    return [
                        'rank' => $index + 1,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'profile_picture' => $user->profile_picture ?? null,
                        'points' => $user->total_points ?? 0
                    ];
                });

            return response()->json(['success' => true, 'data' => $leaderboard]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    // ========================================================================
    // CHALLENGES
    // ========================================================================

    /**
     * Get social challenges
     * GET /api/get-social-challenges
     */
    public function getSocialChallenges(Request $request)
    {
        try {
            $userId = auth()->id();
            $status = $request->get('status', 'active'); // active, completed, all

            $query = DB::table('challenges')
                ->orderBy('start_date', 'desc');

            if ($status === 'active') {
                $query->where('end_date', '>=', now());
            } elseif ($status === 'completed') {
                $query->where('end_date', '<', now());
            }

            $challenges = $query->get()->map(function($challenge) use ($userId) {
                $isParticipating = DB::table('challenge_participants')
                    ->where('challenge_id', $challenge->id)
                    ->where('user_id', $userId)
                    ->exists();

                $participantsCount = DB::table('challenge_participants')
                    ->where('challenge_id', $challenge->id)
                    ->count();

                return [
                    'id' => $challenge->id,
                    'name' => $challenge->name,
                    'description' => $challenge->description,
                    'type' => $challenge->type,
                    'goal' => $challenge->goal,
                    'start_date' => $challenge->start_date,
                    'end_date' => $challenge->end_date,
                    'participants_count' => $participantsCount,
                    'is_participating' => $isParticipating,
                    'reward_points' => $challenge->reward_points ?? 0
                ];
            });

            return response()->json(['success' => true, 'data' => $challenges]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }
}
