<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Follow;
use App\Models\Group;
use App\Models\Achievement;
use Carbon\Carbon;

class SocialCommunityController extends Controller
{
    /**
     * Create a new post
     */
    public function createPost(Request $request)
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string|max:5000',
                'type' => 'required|string|in:status,achievement,workout,meal,progress,question',
                'media' => 'nullable|array|max:10',
                'media.*.url' => 'required|string',
                'media.*.type' => 'required|string|in:image,video',
                'workout_id' => 'nullable|integer|exists:workouts,id',
                'meal_id' => 'nullable|integer|exists:meals,id',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:30',
                'visibility' => 'nullable|string|in:public,followers,private',
                'location' => 'nullable|string|max:100'
            ]);

            DB::beginTransaction();

            $post = Post::create([
                'user_id' => Auth::id(),
                'content' => $validated['content'],
                'type' => $validated['type'],
                'workout_id' => $validated['workout_id'] ?? null,
                'meal_id' => $validated['meal_id'] ?? null,
                'visibility' => $validated['visibility'] ?? 'public',
                'location' => $validated['location'] ?? null,
                'is_active' => true
            ]);

            // Handle media attachments
            if (!empty($validated['media'])) {
                foreach ($validated['media'] as $media) {
                    DB::table('post_media')->insert([
                        'post_id' => $post->id,
                        'media_url' => $media['url'],
                        'media_type' => $media['type'],
                        'created_at' => now()
                    ]);
                }
            }

            // Handle tags
            if (!empty($validated['tags'])) {
                foreach ($validated['tags'] as $tag) {
                    $tagRecord = DB::table('tags')->firstOrCreate(
                        ['name' => strtolower($tag)],
                        ['created_at' => now()]
                    );

                    DB::table('post_tags')->insert([
                        'post_id' => $post->id,
                        'tag_id' => $tagRecord->id,
                        'created_at' => now()
                    ]);
                }
            }

            // Award points for posting
            $this->awardPoints(Auth::id(), 'post_created', 10);

            // Notify followers
            $this->notifyFollowers(Auth::id(), $post);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Post created successfully',
                'post' => $post->load(['user', 'media', 'tags'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating post', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get community feed
     */
    public function getFeed(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => 'nullable|string|in:all,following,trending,recent',
                'filter' => 'nullable|string|in:all,workout,meal,progress,achievement',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $userId = Auth::id();
            $type = $validated['type'] ?? 'all';
            $filter = $validated['filter'] ?? 'all';
            $limit = $validated['limit'] ?? 20;

            $query = Post::with(['user', 'media', 'tags'])
                ->where('is_active', true)
                ->where('visibility', '!=', 'private');

            // Apply feed type
            if ($type === 'following') {
                $followingIds = Follow::where('follower_id', $userId)
                    ->where('status', 'accepted')
                    ->pluck('following_id');

                $query->whereIn('user_id', $followingIds);
            } elseif ($type === 'trending') {
                $query->withCount(['likes', 'comments'])
                    ->orderByRaw('(likes_count * 2 + comments_count) DESC');
            }

            // Apply filter
            if ($filter !== 'all') {
                $query->where('type', $filter);
            }

            // Order by recent unless trending
            if ($type !== 'trending') {
                $query->orderBy('created_at', 'desc');
            }

            $posts = $query->paginate($limit);

            // Add engagement data
            foreach ($posts->items() as $post) {
                $post->likes_count = Like::where('likeable_type', 'App\Models\Post')
                    ->where('likeable_id', $post->id)
                    ->count();

                $post->comments_count = Comment::where('post_id', $post->id)->count();

                $post->user_liked = Like::where('likeable_type', 'App\Models\Post')
                    ->where('likeable_id', $post->id)
                    ->where('user_id', $userId)
                    ->exists();

                $post->user_following = Follow::where('follower_id', $userId)
                    ->where('following_id', $post->user_id)
                    ->where('status', 'accepted')
                    ->exists();
            }

            return response()->json([
                'success' => true,
                'feed' => $posts,
                'type' => $type,
                'filter' => $filter
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching feed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch feed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Like/Unlike a post or comment
     */
    public function toggleLike(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:post,comment',
                'id' => 'required|integer'
            ]);

            DB::beginTransaction();

            $likeableType = $validated['type'] === 'post' ? 'App\Models\Post' : 'App\Models\Comment';

            // Check if already liked
            $existingLike = Like::where('user_id', Auth::id())
                ->where('likeable_type', $likeableType)
                ->where('likeable_id', $validated['id'])
                ->first();

            if ($existingLike) {
                $existingLike->delete();
                $action = 'unliked';
                $liked = false;
            } else {
                Like::create([
                    'user_id' => Auth::id(),
                    'likeable_type' => $likeableType,
                    'likeable_id' => $validated['id']
                ]);
                $action = 'liked';
                $liked = true;

                // Award points for first like
                if ($validated['type'] === 'post') {
                    $post = Post::find($validated['id']);
                    if ($post && $post->user_id !== Auth::id()) {
                        $this->awardPoints($post->user_id, 'post_liked', 2);
                    }
                }
            }

            // Get updated count
            $likesCount = Like::where('likeable_type', $likeableType)
                ->where('likeable_id', $validated['id'])
                ->count();

            DB::commit();

            return response()->json([
                'success' => true,
                'action' => $action,
                'liked' => $liked,
                'likes_count' => $likesCount
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error toggling like', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle like',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add comment to a post
     */
    public function addComment(Request $request)
    {
        try {
            $validated = $request->validate([
                'post_id' => 'required|integer|exists:posts,id',
                'content' => 'required|string|max:1000',
                'parent_id' => 'nullable|integer|exists:comments,id'
            ]);

            DB::beginTransaction();

            $comment = Comment::create([
                'post_id' => $validated['post_id'],
                'user_id' => Auth::id(),
                'content' => $validated['content'],
                'parent_id' => $validated['parent_id'] ?? null
            ]);

            // Award points
            $this->awardPoints(Auth::id(), 'comment_added', 3);

            // Notify post owner
            $post = Post::find($validated['post_id']);
            if ($post && $post->user_id !== Auth::id()) {
                $this->notifyUser($post->user_id, 'comment', $comment);
            }

            // Notify parent comment owner if replying
            if (!empty($validated['parent_id'])) {
                $parentComment = Comment::find($validated['parent_id']);
                if ($parentComment && $parentComment->user_id !== Auth::id()) {
                    $this->notifyUser($parentComment->user_id, 'reply', $comment);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'comment' => $comment->load('user')
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error adding comment', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Follow/Unfollow a user
     */
    public function toggleFollow(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id'
            ]);

            if ($validated['user_id'] == Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot follow yourself'
                ], 400);
            }

            DB::beginTransaction();

            // Check existing follow
            $existingFollow = Follow::where('follower_id', Auth::id())
                ->where('following_id', $validated['user_id'])
                ->first();

            if ($existingFollow) {
                $existingFollow->delete();
                $action = 'unfollowed';
                $following = false;
            } else {
                Follow::create([
                    'follower_id' => Auth::id(),
                    'following_id' => $validated['user_id'],
                    'status' => 'accepted' // Or 'pending' if you want approval system
                ]);
                $action = 'followed';
                $following = true;

                // Notify user
                $this->notifyUser($validated['user_id'], 'follow', Auth::user());

                // Award points
                $this->awardPoints(Auth::id(), 'user_followed', 5);
            }

            // Get updated counts
            $followersCount = Follow::where('following_id', $validated['user_id'])
                ->where('status', 'accepted')
                ->count();

            $followingCount = Follow::where('follower_id', $validated['user_id'])
                ->where('status', 'accepted')
                ->count();

            DB::commit();

            return response()->json([
                'success' => true,
                'action' => $action,
                'following' => $following,
                'followers_count' => $followersCount,
                'following_count' => $followingCount
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error toggling follow', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle follow',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user profile with social stats
     */
    public function getUserProfile($userId)
    {
        try {
            $user = User::findOrFail($userId);

            // Get social stats
            $stats = [
                'posts_count' => Post::where('user_id', $userId)->count(),
                'followers_count' => Follow::where('following_id', $userId)
                    ->where('status', 'accepted')->count(),
                'following_count' => Follow::where('follower_id', $userId)
                    ->where('status', 'accepted')->count(),
                'total_likes' => Like::whereHas('likeable', function($query) use ($userId) {
                    $query->where('user_id', $userId);
                })->count(),
                'achievements_count' => Achievement::where('user_id', $userId)->count()
            ];

            // Check if current user follows this user
            $isFollowing = false;
            if (Auth::check() && Auth::id() !== $userId) {
                $isFollowing = Follow::where('follower_id', Auth::id())
                    ->where('following_id', $userId)
                    ->where('status', 'accepted')
                    ->exists();
            }

            // Get recent posts
            $recentPosts = Post::where('user_id', $userId)
                ->where('is_active', true)
                ->with(['media', 'tags'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Get achievements
            $achievements = Achievement::where('user_id', $userId)
                ->orderBy('achieved_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username ?? $user->email,
                    'profile_picture' => $user->profile_picture,
                    'bio' => $user->bio,
                    'location' => $user->location,
                    'joined_date' => $user->created_at,
                    'body_points' => $user->body_points ?? 0
                ],
                'stats' => $stats,
                'is_following' => $isFollowing,
                'recent_posts' => $recentPosts,
                'achievements' => $achievements
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user profile', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search users
     */
    public function searchUsers(Request $request)
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:2|max:100',
                'type' => 'nullable|string|in:all,coaches,users',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $query = User::where('is_active', true)
                ->where(function($q) use ($validated) {
                    $q->where('name', 'like', '%' . $validated['query'] . '%')
                      ->orWhere('email', 'like', '%' . $validated['query'] . '%')
                      ->orWhere('username', 'like', '%' . $validated['query'] . '%');
                });

            // Filter by type
            if (!empty($validated['type']) && $validated['type'] !== 'all') {
                if ($validated['type'] === 'coaches') {
                    $query->where('role', 'coach');
                } else {
                    $query->where('role', 'user');
                }
            }

            $limit = $validated['limit'] ?? 20;
            $users = $query->limit($limit)->get();

            // Add follow status
            foreach ($users as $user) {
                $user->is_following = Follow::where('follower_id', Auth::id())
                    ->where('following_id', $user->id)
                    ->where('status', 'accepted')
                    ->exists();

                $user->followers_count = Follow::where('following_id', $user->id)
                    ->where('status', 'accepted')
                    ->count();
            }

            return response()->json([
                'success' => true,
                'users' => $users,
                'query' => $validated['query']
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching users', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:points,workouts,weight_loss,strength',
                'period' => 'nullable|string|in:week,month,all_time',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $period = $validated['period'] ?? 'month';
            $limit = $validated['limit'] ?? 50;

            $query = User::where('is_active', true);

            // Apply period filter
            $startDate = null;
            if ($period === 'week') {
                $startDate = Carbon::now()->subWeek();
            } elseif ($period === 'month') {
                $startDate = Carbon::now()->subMonth();
            }

            // Build leaderboard based on type
            switch ($validated['type']) {
                case 'points':
                    $query->orderBy('body_points', 'desc');
                    break;

                case 'workouts':
                    $query->withCount(['workoutSessions' => function($q) use ($startDate) {
                        if ($startDate) {
                            $q->where('created_at', '>=', $startDate);
                        }
                        $q->where('status', 'completed');
                    }])->orderBy('workout_sessions_count', 'desc');
                    break;

                case 'weight_loss':
                    // Calculate weight loss
                    $users = $query->get()->map(function($user) use ($startDate) {
                        $weightLoss = $this->calculateWeightLoss($user->id, $startDate);
                        $user->weight_loss = $weightLoss;
                        return $user;
                    })->sortByDesc('weight_loss');
                    break;

                case 'strength':
                    // Calculate strength gains
                    $users = $query->get()->map(function($user) use ($startDate) {
                        $strengthGain = $this->calculateStrengthGain($user->id, $startDate);
                        $user->strength_gain = $strengthGain;
                        return $user;
                    })->sortByDesc('strength_gain');
                    break;
            }

            if (!isset($users)) {
                $users = $query->limit($limit)->get();
            } else {
                $users = $users->take($limit);
            }

            // Add rank and current user position
            $leaderboard = [];
            $rank = 1;
            $currentUserRank = null;

            foreach ($users as $user) {
                $userData = [
                    'rank' => $rank,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'profile_picture' => $user->profile_picture,
                    'body_points' => $user->body_points ?? 0
                ];

                // Add type-specific data
                switch ($validated['type']) {
                    case 'workouts':
                        $userData['workouts_completed'] = $user->workout_sessions_count ?? 0;
                        break;
                    case 'weight_loss':
                        $userData['weight_loss'] = $user->weight_loss ?? 0;
                        break;
                    case 'strength':
                        $userData['strength_gain'] = $user->strength_gain ?? 0;
                        break;
                }

                if ($user->id == Auth::id()) {
                    $currentUserRank = $rank;
                }

                $leaderboard[] = $userData;
                $rank++;
            }

            return response()->json([
                'success' => true,
                'leaderboard' => $leaderboard,
                'current_user_rank' => $currentUserRank,
                'type' => $validated['type'],
                'period' => $period
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching leaderboard', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leaderboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create/Join a group
     */
    public function createGroup(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:groups,name',
                'description' => 'required|string|max:500',
                'type' => 'required|string|in:public,private,invite_only',
                'category' => 'required|string|in:weight_loss,muscle_gain,running,yoga,nutrition,general',
                'cover_image' => 'nullable|string',
                'rules' => 'nullable|array',
                'rules.*' => 'string|max:200'
            ]);

            DB::beginTransaction();

            $group = Group::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'type' => $validated['type'],
                'category' => $validated['category'],
                'cover_image' => $validated['cover_image'] ?? null,
                'rules' => $validated['rules'] ?? [],
                'created_by' => Auth::id(),
                'is_active' => true
            ]);

            // Add creator as admin member
            DB::table('group_members')->insert([
                'group_id' => $group->id,
                'user_id' => Auth::id(),
                'role' => 'admin',
                'joined_at' => now(),
                'created_at' => now()
            ]);

            // Award points for creating group
            $this->awardPoints(Auth::id(), 'group_created', 20);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'group' => $group
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating group', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Join a group
     */
    public function joinGroup(Request $request)
    {
        try {
            $validated = $request->validate([
                'group_id' => 'required|integer|exists:groups,id'
            ]);

            DB::beginTransaction();

            $group = Group::findOrFail($validated['group_id']);

            // Check if already a member
            $existingMember = DB::table('group_members')
                ->where('group_id', $group->id)
                ->where('user_id', Auth::id())
                ->first();

            if ($existingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already a member of this group'
                ], 400);
            }

            // Check group type
            $status = 'active';
            if ($group->type === 'invite_only') {
                $status = 'pending';
            }

            // Add member
            DB::table('group_members')->insert([
                'group_id' => $group->id,
                'user_id' => Auth::id(),
                'role' => 'member',
                'status' => $status,
                'joined_at' => $status === 'active' ? now() : null,
                'created_at' => now()
            ]);

            // Update member count
            $group->increment('members_count');

            // Award points if joined
            if ($status === 'active') {
                $this->awardPoints(Auth::id(), 'group_joined', 5);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $status === 'active' ? 'Joined group successfully' : 'Join request sent',
                'status' => $status,
                'group' => $group
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error joining group', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to join group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Share achievement
     */
    public function shareAchievement(Request $request)
    {
        try {
            $validated = $request->validate([
                'achievement_type' => 'required|string|in:weight_loss,workout_streak,personal_record,goal_reached,challenge_completed',
                'title' => 'required|string|max:200',
                'description' => 'required|string|max:1000',
                'value' => 'nullable|numeric',
                'unit' => 'nullable|string|max:20',
                'media_url' => 'nullable|string',
                'share_to_feed' => 'nullable|boolean'
            ]);

            DB::beginTransaction();

            // Create achievement
            $achievement = Achievement::create([
                'user_id' => Auth::id(),
                'type' => $validated['achievement_type'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'value' => $validated['value'] ?? null,
                'unit' => $validated['unit'] ?? null,
                'media_url' => $validated['media_url'] ?? null,
                'achieved_at' => now()
            ]);

            // Share to feed if requested
            if ($validated['share_to_feed'] ?? true) {
                $post = Post::create([
                    'user_id' => Auth::id(),
                    'content' => "🏆 Achievement Unlocked: {$validated['title']}\n\n{$validated['description']}",
                    'type' => 'achievement',
                    'achievement_id' => $achievement->id,
                    'visibility' => 'public',
                    'is_active' => true
                ]);

                if (!empty($validated['media_url'])) {
                    DB::table('post_media')->insert([
                        'post_id' => $post->id,
                        'media_url' => $validated['media_url'],
                        'media_type' => 'image',
                        'created_at' => now()
                    ]);
                }
            }

            // Award bonus points for sharing achievement
            $this->awardPoints(Auth::id(), 'achievement_shared', 15);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Achievement shared successfully',
                'achievement' => $achievement,
                'post' => $post ?? null
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error sharing achievement', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to share achievement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function awardPoints($userId, $action, $points)
    {
        try {
            $user = User::find($userId);
            if ($user) {
                $user->increment('body_points', $points);

                DB::table('point_logs')->insert([
                    'user_id' => $userId,
                    'action' => $action,
                    'points' => $points,
                    'created_at' => now()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to award points', ['error' => $e->getMessage()]);
        }
    }

    private function notifyFollowers($userId, $post)
    {
        $followers = Follow::where('following_id', $userId)
            ->where('status', 'accepted')
            ->pluck('follower_id');

        foreach ($followers as $followerId) {
            $this->notifyUser($followerId, 'new_post', $post);
        }
    }

    private function notifyUser($userId, $type, $data)
    {
        try {
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'type' => $type,
                'data' => json_encode($data),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send notification', ['error' => $e->getMessage()]);
        }
    }

    private function calculateWeightLoss($userId, $startDate)
    {
        // Implement weight loss calculation logic
        return 0;
    }

    private function calculateStrengthGain($userId, $startDate)
    {
        // Implement strength gain calculation logic
        return 0;
    }
}