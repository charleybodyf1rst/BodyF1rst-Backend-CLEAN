<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Friendship Controller
 * Handles friend requests, friendships, and user search
 */
class FriendshipController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get Friends List
     * GET /api/friends
     */
    public function getFriends(Request $request)
    {
        try {
            $userId = Auth::id();

            $friends = Friendship::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('friend_id', $userId);
            })
            ->where('status', 'accepted')
            ->with(['user', 'friend'])
            ->get()
            ->map(function ($friendship) use ($userId) {
                // Get the friend (not the current user)
                $friend = $friendship->user_id === $userId
                    ? $friendship->friend
                    : $friendship->user;

                return [
                    'friendshipId' => $friendship->id,
                    'user' => [
                        'id' => $friend->id,
                        'name' => $friend->name,
                        'email' => $friend->email,
                        'avatar' => $friend->profile_photo_url ?? null,
                        'level' => $friend->level ?? 1,
                        'bodyPoints' => $friend->body_points ?? 0,
                        'currentStreak' => $friend->current_streak ?? 0,
                    ],
                    'friendsSince' => $friendship->accepted_at->toIso8601String(),
                    'friendsSinceDays' => $friendship->accepted_at->diffInDays(now()),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'friends' => $friends,
                    'totalFriends' => $friends->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load friends list',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Pending Friend Requests (Received)
     * GET /api/friends/requests/pending
     */
    public function getPendingRequests(Request $request)
    {
        try {
            $userId = Auth::id();

            $requests = Friendship::where('friend_id', $userId)
                ->where('status', 'pending')
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($friendship) {
                    return [
                        'requestId' => $friendship->id,
                        'from' => [
                            'id' => $friendship->user->id,
                            'name' => $friendship->user->name,
                            'email' => $friendship->user->email,
                            'avatar' => $friendship->user->profile_photo_url ?? null,
                            'level' => $friendship->user->level ?? 1,
                        ],
                        'message' => $friendship->message ?? null,
                        'requestedAt' => $friendship->created_at->toIso8601String(),
                        'timeAgo' => $friendship->created_at->diffForHumans(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'requests' => $requests,
                    'totalRequests' => $requests->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load pending requests',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Sent Friend Requests
     * GET /api/friends/requests/sent
     */
    public function getSentRequests(Request $request)
    {
        try {
            $userId = Auth::id();

            $requests = Friendship::where('user_id', $userId)
                ->where('status', 'pending')
                ->with('friend')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($friendship) {
                    return [
                        'requestId' => $friendship->id,
                        'to' => [
                            'id' => $friendship->friend->id,
                            'name' => $friendship->friend->name,
                            'email' => $friendship->friend->email,
                            'avatar' => $friendship->friend->profile_photo_url ?? null,
                        ],
                        'message' => $friendship->message ?? null,
                        'requestedAt' => $friendship->created_at->toIso8601String(),
                        'timeAgo' => $friendship->created_at->diffForHumans(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'requests' => $requests,
                    'totalRequests' => $requests->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load sent requests',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Send Friend Request
     * POST /api/friends/request
     */
    public function sendFriendRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'friend_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = Auth::id();
            $friendId = $request->friend_id;

            // Can't friend yourself
            if ($userId === $friendId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot send a friend request to yourself',
                ], 400);
            }

            // Check if friendship already exists
            $existingFriendship = Friendship::where(function ($query) use ($userId, $friendId) {
                $query->where(function ($q) use ($userId, $friendId) {
                    $q->where('user_id', $userId)
                      ->where('friend_id', $friendId);
                })->orWhere(function ($q) use ($userId, $friendId) {
                    $q->where('user_id', $friendId)
                      ->where('friend_id', $userId);
                });
            })->first();

            if ($existingFriendship) {
                if ($existingFriendship->status === 'accepted') {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are already friends with this user',
                    ], 400);
                } elseif ($existingFriendship->status === 'pending') {
                    return response()->json([
                        'success' => false,
                        'message' => 'A friend request is already pending',
                    ], 400);
                }
            }

            // Create friend request
            $friendship = Friendship::create([
                'user_id' => $userId,
                'friend_id' => $friendId,
                'status' => 'pending',
                'message' => $request->message,
            ]);

            // Create notification for the friend
            $this->createNotification($friendId, $userId, 'friend_request', $friendship->id);

            return response()->json([
                'success' => true,
                'message' => 'Friend request sent successfully',
                'data' => [
                    'requestId' => $friendship->id,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send friend request',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Accept Friend Request
     * POST /api/friends/accept/{id}
     */
    public function acceptFriendRequest($requestId)
    {
        try {
            $userId = Auth::id();

            $friendship = Friendship::where('id', $requestId)
                ->where('friend_id', $userId)
                ->where('status', 'pending')
                ->first();

            if (!$friendship) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friend request not found',
                ], 404);
            }

            // Accept the friendship
            $friendship->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            // Create notification for the requester
            $this->createNotification($friendship->user_id, $userId, 'friend_request_accepted', $friendship->id);

            return response()->json([
                'success' => true,
                'message' => 'Friend request accepted',
                'data' => [
                    'friendshipId' => $friendship->id,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept friend request',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Reject Friend Request
     * POST /api/friends/reject/{id}
     */
    public function rejectFriendRequest($requestId)
    {
        try {
            $userId = Auth::id();

            $friendship = Friendship::where('id', $requestId)
                ->where('friend_id', $userId)
                ->where('status', 'pending')
                ->first();

            if (!$friendship) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friend request not found',
                ], 404);
            }

            // Delete the friendship request
            $friendship->delete();

            return response()->json([
                'success' => true,
                'message' => 'Friend request rejected',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject friend request',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Unfriend / Remove Friend
     * DELETE /api/friends/{id}
     */
    public function unfriend($friendshipId)
    {
        try {
            $userId = Auth::id();

            $friendship = Friendship::where('id', $friendshipId)
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhere('friend_id', $userId);
                })
                ->where('status', 'accepted')
                ->first();

            if (!$friendship) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friendship not found',
                ], 404);
            }

            // Delete the friendship
            $friendship->delete();

            return response()->json([
                'success' => true,
                'message' => 'Friend removed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove friend',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Search Users
     * GET /api/users/search
     */
    public function searchUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = Auth::id();
            $query = $request->query;
            $limit = $request->limit ?? 20;

            // Get IDs of current friends and pending requests
            $friendIds = Friendship::where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('friend_id', $userId);
            })
            ->where('status', 'accepted')
            ->get()
            ->map(function ($friendship) use ($userId) {
                return $friendship->user_id === $userId ? $friendship->friend_id : $friendship->user_id;
            })
            ->toArray();

            $pendingIds = Friendship::where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('friend_id', $userId);
            })
            ->where('status', 'pending')
            ->get()
            ->map(function ($friendship) use ($userId) {
                return $friendship->user_id === $userId ? $friendship->friend_id : $friendship->user_id;
            })
            ->toArray();

            // Search users
            $users = User::where('id', '!=', $userId)
                ->where('status', 'active')
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get()
                ->map(function ($user) use ($friendIds, $pendingIds) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->profile_photo_url ?? null,
                        'level' => $user->level ?? 1,
                        'bodyPoints' => $user->body_points ?? 0,
                        'relationshipStatus' => $this->getRelationshipStatus($user->id, $friendIds, $pendingIds),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'totalResults' => $users->count(),
                    'query' => $query,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search users',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Helper Methods

    protected function getRelationshipStatus($userId, $friendIds, $pendingIds)
    {
        if (in_array($userId, $friendIds)) {
            return 'friends';
        } elseif (in_array($userId, $pendingIds)) {
            return 'pending';
        }
        return 'none';
    }

    protected function createNotification($recipientId, $senderId, $type, $referenceId)
    {
        try {
            DB::table('notifications')->insert([
                'user_id' => $recipientId,
                'type' => $type,
                'sender_id' => $senderId,
                'reference_id' => $referenceId,
                'reference_type' => 'friendship',
                'read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silent fail - notification is not critical
            \Log::error('Failed to create friendship notification: ' . $e->getMessage());
        }
    }
}
