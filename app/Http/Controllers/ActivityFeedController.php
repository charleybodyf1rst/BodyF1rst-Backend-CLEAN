<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityFeed;
use App\Models\ActivityLike;
use App\Models\ActivityComment;
use App\Models\Friendship;
use App\Models\WorkoutLog;
use App\Models\Achievement;
use App\Models\Milestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Activity Feed Controller
 * Handles social activity feed, likes, and comments
 */
class ActivityFeedController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get Activity Feed
     * GET /api/activity-feed
     */
    public function getActivityFeed(Request $request)
    {
        try {
            $userId = Auth::id();
            $filter = $request->input('filter', 'all'); // all, friends, mine
            $limit = $request->input('limit', 20);
            $offset = $request->input('offset', 0);

            $query = ActivityFeed::query();

            // Apply filters
            switch ($filter) {
                case 'friends':
                    $friendIds = $this->getFriendIds($userId);
                    $friendIds[] = $userId; // Include own activities
                    $query->whereIn('user_id', $friendIds);
                    break;
                case 'mine':
                    $query->where('user_id', $userId);
                    break;
                case 'all':
                default:
                    // Show all public activities
                    $query->where('visibility', 'public');
                    break;
            }

            $activities = $query->with(['user', 'likes', 'comments.user'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($activity) use ($userId) {
                    return $this->formatActivity($activity, $userId);
                });

            return response()->json([
                'success' => true,
                'data' => $activities,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'hasMore' => $activities->count() === $limit,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load activity feed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Create Activity Post
     * POST /api/activity-feed
     */
    public function createActivity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:workout_completed,achievement_earned,milestone_reached,progress_photo,personal_record,weight_goal,custom_post',
            'content' => 'nullable|string|max:1000',
            'media_url' => 'nullable|url',
            'media_type' => 'nullable|string|in:image,video',
            'visibility' => 'nullable|string|in:public,friends,private',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = Auth::id();

            $activity = ActivityFeed::create([
                'user_id' => $userId,
                'type' => $request->type,
                'content' => $request->content,
                'media_url' => $request->media_url,
                'media_type' => $request->media_type,
                'visibility' => $request->visibility ?? 'public',
                'metadata' => $request->metadata ?? [],
            ]);

            $activity->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Activity posted successfully',
                'data' => $this->formatActivity($activity, $userId),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create activity',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Like Activity
     * POST /api/activity-feed/like/{id}
     */
    public function likeActivity($activityId)
    {
        try {
            $userId = Auth::id();

            // Check if activity exists
            $activity = ActivityFeed::find($activityId);
            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found',
                ], 404);
            }

            // Check if already liked
            $existingLike = ActivityLike::where('activity_id', $activityId)
                ->where('user_id', $userId)
                ->first();

            if ($existingLike) {
                // Unlike
                $existingLike->delete();
                $action = 'unliked';
            } else {
                // Like
                ActivityLike::create([
                    'activity_id' => $activityId,
                    'user_id' => $userId,
                ]);
                $action = 'liked';

                // Create notification for activity owner
                if ($activity->user_id !== $userId) {
                    $this->createNotification($activity->user_id, $userId, 'activity_liked', $activityId);
                }
            }

            // Get updated like count
            $likeCount = ActivityLike::where('activity_id', $activityId)->count();

            return response()->json([
                'success' => true,
                'message' => "Activity $action",
                'data' => [
                    'action' => $action,
                    'likeCount' => $likeCount,
                    'isLiked' => $action === 'liked',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to like activity',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Comment on Activity
     * POST /api/activity-feed/comment/{id}
     */
    public function commentOnActivity(Request $request, $activityId)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = Auth::id();

            // Check if activity exists
            $activity = ActivityFeed::find($activityId);
            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found',
                ], 404);
            }

            $comment = ActivityComment::create([
                'activity_id' => $activityId,
                'user_id' => $userId,
                'comment' => $request->comment,
            ]);

            $comment->load('user');

            // Create notification for activity owner
            if ($activity->user_id !== $userId) {
                $this->createNotification($activity->user_id, $userId, 'activity_commented', $activityId);
            }

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'data' => $this->formatComment($comment),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Delete Activity
     * DELETE /api/activity-feed/{id}
     */
    public function deleteActivity($activityId)
    {
        try {
            $userId = Auth::id();

            $activity = ActivityFeed::find($activityId);
            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found',
                ], 404);
            }

            // Verify ownership
            if ($activity->user_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this activity',
                ], 403);
            }

            // Delete associated likes and comments
            ActivityLike::where('activity_id', $activityId)->delete();
            ActivityComment::where('activity_id', $activityId)->delete();

            $activity->delete();

            return response()->json([
                'success' => true,
                'message' => 'Activity deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete activity',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Delete Comment
     * DELETE /api/activity-feed/comment/{id}
     */
    public function deleteComment($commentId)
    {
        try {
            $userId = Auth::id();

            $comment = ActivityComment::find($commentId);
            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            // Verify ownership or activity ownership
            $activity = ActivityFeed::find($comment->activity_id);
            if ($comment->user_id !== $userId && $activity->user_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this comment',
                ], 403);
            }

            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Auto-generate activities from user actions
     * This can be called internally when certain events occur
     */
    public function generateActivityFromEvent($userId, $eventType, $data = [])
    {
        try {
            $content = $this->generateActivityContent($eventType, $data);

            $activity = ActivityFeed::create([
                'user_id' => $userId,
                'type' => $eventType,
                'content' => $content,
                'media_url' => $data['media_url'] ?? null,
                'media_type' => $data['media_type'] ?? null,
                'visibility' => $data['visibility'] ?? 'public',
                'metadata' => $data,
                'auto_generated' => true,
            ]);

            return $activity;

        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            \Log::error('Failed to generate activity: ' . $e->getMessage());
            return null;
        }
    }

    // Helper Methods

    protected function getFriendIds($userId)
    {
        return Friendship::where(function ($query) use ($userId) {
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

    protected function formatActivity($activity, $currentUserId)
    {
        return [
            'id' => $activity->id,
            'user' => [
                'id' => $activity->user->id,
                'name' => $activity->user->name,
                'email' => $activity->user->email,
                'avatar' => $activity->user->profile_photo_url ?? null,
            ],
            'type' => $activity->type,
            'content' => $activity->content,
            'mediaUrl' => $activity->media_url,
            'mediaType' => $activity->media_type,
            'visibility' => $activity->visibility,
            'metadata' => $activity->metadata,
            'likeCount' => $activity->likes->count(),
            'commentCount' => $activity->comments->count(),
            'isLiked' => $activity->likes->contains('user_id', $currentUserId),
            'comments' => $activity->comments->take(3)->map(function ($comment) {
                return $this->formatComment($comment);
            }),
            'createdAt' => $activity->created_at->toIso8601String(),
            'timeAgo' => $activity->created_at->diffForHumans(),
        ];
    }

    protected function formatComment($comment)
    {
        return [
            'id' => $comment->id,
            'user' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'avatar' => $comment->user->profile_photo_url ?? null,
            ],
            'comment' => $comment->comment,
            'createdAt' => $comment->created_at->toIso8601String(),
            'timeAgo' => $comment->created_at->diffForHumans(),
        ];
    }

    protected function generateActivityContent($eventType, $data)
    {
        return match($eventType) {
            'workout_completed' => "Completed a {$data['workout_name']} workout!",
            'achievement_earned' => "Earned the '{$data['achievement_name']}' achievement!",
            'milestone_reached' => "Reached a new milestone: {$data['milestone_name']}",
            'progress_photo' => "Shared a progress photo",
            'personal_record' => "Set a new PR: {$data['exercise']} - {$data['value']}",
            'weight_goal' => "Reached weight goal: {$data['weight']} lbs",
            'custom_post' => $data['content'] ?? '',
            default => 'Shared an update',
        };
    }

    protected function createNotification($recipientId, $senderId, $type, $activityId)
    {
        // This would integrate with the notifications system
        // Implementation depends on existing notification structure
        try {
            DB::table('notifications')->insert([
                'user_id' => $recipientId,
                'type' => $type,
                'sender_id' => $senderId,
                'reference_id' => $activityId,
                'reference_type' => 'activity',
                'read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silent fail - notification is not critical
            \Log::error('Failed to create notification: ' . $e->getMessage());
        }
    }
}
