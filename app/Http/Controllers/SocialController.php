<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\UserConnection;
use App\Models\ContactDiscovery;
use App\Models\FriendActivityFeed;
use App\Models\User;

class SocialController extends Controller
{
    /**
     * Discover friends by email or phone contacts
     */
    public function discoverFriends(Request $request)
    {
        $validated = $request->validate([
            'contact_type' => 'required|in:email,phone',
            'contacts' => 'required|array',
            'contacts.*' => 'required|string'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Find matching users
        $matches = ContactDiscovery::findMatchingUsers(
            $validated['contact_type'],
            $validated['contacts']
        );

        // Filter out already connected users and self
        $connectedUserIds = UserConnection::forUser($user->id)
            ->pluck('user_id')
            ->merge(UserConnection::forUser($user->id)->pluck('friend_id'))
            ->unique()
            ->toArray();

        $suggestions = $matches->filter(function($discovery) use ($user, $connectedUserIds) {
            return $discovery->user_id != $user->id &&
                   !in_array($discovery->user_id, $connectedUserIds);
        })->map(function($discovery) {
            return [
                'user_id' => $discovery->user->id,
                'name' => $discovery->user->first_name . ' ' . $discovery->user->last_name,
                'email' => $discovery->user->email,
                'avatar' => $discovery->user->avatar,
                'match_source' => $discovery->contact_type
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Found ' . $suggestions->count() . ' friend suggestions',
            'suggestions' => $suggestions
        ]);
    }

    /**
     * Add contacts for discovery
     */
    public function addContactsForDiscovery(Request $request)
    {
        $validated = $request->validate([
            'emails' => 'nullable|array',
            'emails.*' => 'email',
            'phones' => 'nullable|array',
            'phones.*' => 'string'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Add user's own email and phone for discovery
        if ($user->email) {
            ContactDiscovery::firstOrCreate([
                'user_id' => $user->id,
                'contact_type' => 'email',
                'contact_value_hash' => ContactDiscovery::hashContact($user->email)
            ], [
                'display_name' => $user->first_name . ' ' . $user->last_name,
                'discoverable' => true
            ]);
        }

        if ($user->phone) {
            ContactDiscovery::firstOrCreate([
                'user_id' => $user->id,
                'contact_type' => 'phone',
                'contact_value_hash' => ContactDiscovery::hashContact($user->phone)
            ], [
                'display_name' => $user->first_name . ' ' . $user->last_name,
                'discoverable' => true
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contacts added for discovery'
        ]);
    }

    /**
     * Send friend request
     */
    public function sendFriendRequest(Request $request)
    {
        $validated = $request->validate([
            'friend_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:500'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Check if already connected or pending
        $existing = UserConnection::where(function($q) use ($user, $validated) {
            $q->where('user_id', $user->id)->where('friend_id', $validated['friend_id']);
        })->orWhere(function($q) use ($user, $validated) {
            $q->where('user_id', $validated['friend_id'])->where('friend_id', $user->id);
        })->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Connection already exists or pending'
            ], 400);
        }

        // Cannot add yourself
        if ($user->id == $validated['friend_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add yourself as a friend'
            ], 400);
        }

        $connection = UserConnection::create([
            'user_id' => $user->id,
            'friend_id' => $validated['friend_id'],
            'status' => 'pending',
            'connection_source' => 'manual'
        ]);

        // TODO: Send notification to friend

        return response()->json([
            'success' => true,
            'message' => 'Friend request sent successfully',
            'connection' => $connection
        ], 201);
    }

    /**
     * Accept friend request
     */
    public function acceptFriendRequest($connectionId)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $connection = UserConnection::where('id', $connectionId)
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $connection->accept();

        // Create activity feed entry
        FriendActivityFeed::create([
            'user_id' => $user->id,
            'activity_type' => 'new_personal_record', // Using as placeholder for "new connection"
            'activity_description' => 'Connected with ' . $connection->user->first_name . ' ' . $connection->user->last_name,
            'activity_icon' => 'people',
            'is_public' => true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Friend request accepted',
            'connection' => $connection->load(['user', 'friend'])
        ]);
    }

    /**
     * Reject friend request
     */
    public function rejectFriendRequest($connectionId)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $connection = UserConnection::where('id', $connectionId)
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $connection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Friend request rejected'
        ]);
    }

    /**
     * Get friends list
     */
    public function getFriends(Request $request)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $connections = UserConnection::where(function($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('friend_id', $user->id);
        })
        ->where('status', 'accepted')
        ->with(['user', 'friend'])
        ->get()
        ->map(function($connection) use ($user) {
            $friend = $connection->user_id == $user->id ? $connection->friend : $connection->user;

            return [
                'connection_id' => $connection->id,
                'friend_id' => $friend->id,
                'name' => $friend->first_name . ' ' . $friend->last_name,
                'email' => $friend->email,
                'avatar' => $friend->avatar,
                'can_view_progression' => $connection->can_view_progression,
                'can_message' => $connection->can_message,
                'share_workouts' => $connection->share_workouts,
                'share_nutrition' => $connection->share_nutrition,
                'share_achievements' => $connection->share_achievements,
                'connected_at' => $connection->connected_at
            ];
        });

        return response()->json([
            'success' => true,
            'friends' => $connections
        ]);
    }

    /**
     * Get pending friend requests (received)
     */
    public function getPendingRequests()
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $pendingRequests = UserConnection::where('friend_id', $user->id)
            ->where('status', 'pending')
            ->with('user')
            ->get()
            ->map(function($connection) {
                return [
                    'connection_id' => $connection->id,
                    'user_id' => $connection->user->id,
                    'name' => $connection->user->first_name . ' ' . $connection->user->last_name,
                    'email' => $connection->user->email,
                    'avatar' => $connection->user->avatar,
                    'requested_at' => $connection->created_at
                ];
            });

        return response()->json([
            'success' => true,
            'pending_requests' => $pendingRequests
        ]);
    }

    /**
     * Update friend connection settings
     */
    public function updateConnectionSettings(Request $request, $connectionId)
    {
        $validated = $request->validate([
            'can_view_progression' => 'boolean',
            'can_message' => 'boolean',
            'share_workouts' => 'boolean',
            'share_nutrition' => 'boolean',
            'share_achievements' => 'boolean'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        $connection = UserConnection::where('id', $connectionId)
            ->where(function($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('friend_id', $user->id);
            })
            ->where('status', 'accepted')
            ->firstOrFail();

        $connection->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Connection settings updated',
            'connection' => $connection
        ]);
    }

    /**
     * Remove friend connection
     */
    public function removeFriend($connectionId)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $connection = UserConnection::where('id', $connectionId)
            ->where(function($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('friend_id', $user->id);
            })
            ->firstOrFail();

        $connection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Friend removed successfully'
        ]);
    }

    /**
     * Get friend activity feed
     */
    public function getActivityFeed(Request $request)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $limit = $request->input('limit', 20);

        $activities = FriendActivityFeed::getFriendsFeed($user->id, $limit);

        return response()->json([
            'success' => true,
            'activities' => $activities
        ]);
    }

    /**
     * Block a user
     */
    public function blockUser(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Find or create connection
        $connection = UserConnection::where(function($q) use ($user, $validated) {
            $q->where('user_id', $user->id)->where('friend_id', $validated['user_id']);
        })->orWhere(function($q) use ($user, $validated) {
            $q->where('user_id', $validated['user_id'])->where('friend_id', $user->id);
        })->first();

        if ($connection) {
            $connection->block();
        } else {
            UserConnection::create([
                'user_id' => $user->id,
                'friend_id' => $validated['user_id'],
                'status' => 'blocked',
                'connection_source' => 'manual'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User blocked successfully'
        ]);
    }

    /**
     * Report a post/activity for inappropriate content
     * POST /api/social/posts/{id}/report
     */
    public function reportPost(Request $request, $postId)
    {
        $validated = $request->validate([
            'reason' => 'required|string|in:spam,inappropriate,harassment,misleading,other',
            'description' => 'nullable|string|max:500'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Create report
        DB::table('social_post_reports')->insert([
            'post_id' => $postId,
            'reporter_id' => $user->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post reported successfully. Our team will review it.'
        ]);
    }

    /**
     * Get trending posts/activities
     * GET /api/social/trending
     */
    public function getTrendingPosts(Request $request)
    {
        $limit = $request->query('limit', 20);
        $timeframe = $request->query('timeframe', '7days'); // 24hours, 7days, 30days

        $since = match($timeframe) {
            '24hours' => now()->subDay(),
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            default => now()->subDays(7)
        };

        // Get posts with most engagement (likes + comments) in timeframe
        $trendingPosts = DB::table('social_activities')
            ->select('social_activities.*',
                DB::raw('(likes_count + comments_count) as engagement_score'))
            ->where('created_at', '>=', $since)
            ->where('is_public', true)
            ->orderBy('engagement_score', 'desc')
            ->limit($limit)
            ->get();

        // Populate user details
        foreach ($trendingPosts as $post) {
            $user = DB::table('users')->find($post->user_id);
            if ($user) {
                $post->username = $user->first_name . ' ' . $user->last_name;
                $post->user_avatar = $user->avatar;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $trendingPosts,
            'count' => $trendingPosts->count(),
            'timeframe' => $timeframe
        ]);
    }

    /**
     * Invite friend to a group
     * POST /api/social/groups/{id}/invite
     */
    public function inviteToGroup(Request $request, $groupId)
    {
        $validated = $request->validate([
            'friend_id' => 'required|exists:users,id'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Check if group exists
        $group = DB::table('social_groups')->find($groupId);
        if (!$group) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found'
            ], 404);
        }

        // Check if user is member of the group
        $isMember = DB::table('social_group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'success' => false,
                'message' => 'You must be a member to invite others'
            ], 403);
        }

        // Check if friend already invited or member
        $alreadyInvited = DB::table('social_group_invites')
            ->where('group_id', $groupId)
            ->where('invited_user_id', $validated['friend_id'])
            ->where('status', 'pending')
            ->exists();

        if ($alreadyInvited) {
            return response()->json([
                'success' => false,
                'message' => 'Friend already invited to this group'
            ], 409);
        }

        // Create invitation
        DB::table('social_group_invites')->insert([
            'group_id' => $groupId,
            'inviter_id' => $user->id,
            'invited_user_id' => $validated['friend_id'],
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully'
        ]);
    }

    /**
     * Get suggested friends based on mutual connections, interests, location
     * GET /api/social/suggested-friends
     */
    public function getSuggestedFriends(Request $request)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();
        $limit = $request->query('limit', 10);

        // Get current friends
        $friendIds = UserConnection::forUser($user->id)
            ->where('status', 'accepted')
            ->pluck('friend_id')
            ->merge(UserConnection::where('friend_id', $user->id)
                ->where('status', 'accepted')
                ->pluck('user_id'))
            ->unique()
            ->toArray();

        // Get pending requests (don't suggest these)
        $pendingIds = UserConnection::forUser($user->id)
            ->whereIn('status', ['pending', 'blocked'])
            ->pluck('friend_id')
            ->merge(UserConnection::where('friend_id', $user->id)
                ->whereIn('status', ['pending', 'blocked'])
                ->pluck('user_id'))
            ->unique()
            ->toArray();

        $excludeIds = array_merge($friendIds, $pendingIds, [$user->id]);

        // Find users with mutual friends
        $suggestions = DB::table('users')
            ->select('users.*', DB::raw('COUNT(DISTINCT uc.id) as mutual_friends_count'))
            ->leftJoin('user_connections as uc', function($join) use ($friendIds) {
                $join->on('users.id', '=', 'uc.user_id')
                    ->whereIn('uc.friend_id', $friendIds)
                    ->where('uc.status', 'accepted');
            })
            ->whereNotIn('users.id', $excludeIds)
            ->groupBy('users.id')
            ->orderBy('mutual_friends_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($suggestion) {
                return [
                    'user_id' => $suggestion->id,
                    'name' => $suggestion->first_name . ' ' . $suggestion->last_name,
                    'email' => $suggestion->email,
                    'avatar' => $suggestion->avatar,
                    'bio' => $suggestion->bio,
                    'mutual_friends' => (int) $suggestion->mutual_friends_count,
                    'reason' => $suggestion->mutual_friends_count > 0
                        ? "{$suggestion->mutual_friends_count} mutual friends"
                        : 'Recommended for you'
                ];
            });

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
            'count' => $suggestions->count()
        ]);
    }

    /**
     * Update profile visibility settings
     * POST /api/social/profile/visibility-settings
     */
    public function updateVisibilitySettings(Request $request)
    {
        $validated = $request->validate([
            'profile_visibility' => 'nullable|in:public,friends,private',
            'activity_visibility' => 'nullable|in:public,friends,private',
            'show_email' => 'nullable|boolean',
            'show_phone' => 'nullable|boolean',
            'show_location' => 'nullable|boolean',
            'show_workouts' => 'nullable|boolean',
            'show_nutrition' => 'nullable|boolean',
            'show_achievements' => 'nullable|boolean',
            'allow_friend_requests' => 'nullable|boolean',
            'allow_messages' => 'nullable|boolean'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Get or create privacy settings
        $settings = DB::table('user_privacy_settings')
            ->where('user_id', $user->id)
            ->first();

        $settingsData = [
            'user_id' => $user->id,
            'profile_visibility' => $validated['profile_visibility'] ?? ($settings->profile_visibility ?? 'public'),
            'activity_visibility' => $validated['activity_visibility'] ?? ($settings->activity_visibility ?? 'friends'),
            'show_email' => $validated['show_email'] ?? ($settings->show_email ?? false),
            'show_phone' => $validated['show_phone'] ?? ($settings->show_phone ?? false),
            'show_location' => $validated['show_location'] ?? ($settings->show_location ?? false),
            'show_workouts' => $validated['show_workouts'] ?? ($settings->show_workouts ?? true),
            'show_nutrition' => $validated['show_nutrition'] ?? ($settings->show_nutrition ?? true),
            'show_achievements' => $validated['show_achievements'] ?? ($settings->show_achievements ?? true),
            'allow_friend_requests' => $validated['allow_friend_requests'] ?? ($settings->allow_friend_requests ?? true),
            'allow_messages' => $validated['allow_messages'] ?? ($settings->allow_messages ?? true),
            'updated_at' => now()
        ];

        if ($settings) {
            DB::table('user_privacy_settings')
                ->where('user_id', $user->id)
                ->update($settingsData);
        } else {
            $settingsData['created_at'] = now();
            DB::table('user_privacy_settings')->insert($settingsData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Privacy settings updated successfully',
            'data' => $settingsData
        ]);
    }

    /**
     * Delete a post/activity
     * DELETE /api/social/posts/{id}
     */
    public function deletePost($postId)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Find the post
        $post = DB::table('social_activities')->find($postId);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found'
            ], 404);
        }

        // Check ownership
        if ($post->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own posts'
            ], 403);
        }

        // Delete associated data
        DB::table('social_activity_likes')->where('activity_id', $postId)->delete();
        DB::table('social_activity_comments')->where('activity_id', $postId)->delete();

        // Delete the post
        DB::table('social_activities')->where('id', $postId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully'
        ]);
    }
}
