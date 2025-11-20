<?php

/**
 * MISSING SOCIAL CONTROLLER METHODS
 *
 * Add these methods to app/Http/Controllers/SocialController.php
 * before the closing brace }
 */

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

/**
 * ROUTES TO ADD TO routes/api.php (inside Route::prefix('social')->middleware(['auth:api'])->group(...))
 *
 * Route::post('/posts/{id}/report', [SocialController::class, 'reportPost']);
 * Route::get('/trending', [SocialController::class, 'getTrendingPosts']);
 * Route::post('/groups/{id}/invite', [SocialController::class, 'inviteToGroup']);
 * Route::get('/suggested-friends', [SocialController::class, 'getSuggestedFriends']);
 * Route::post('/profile/visibility-settings', [SocialController::class, 'updateVisibilitySettings']);
 * Route::delete('/posts/{id}', [SocialController::class, 'deletePost']);
 */
