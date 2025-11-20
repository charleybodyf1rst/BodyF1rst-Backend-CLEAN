<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SocialShare;
use App\Models\SocialShareReward;
use App\Models\UserRewardClaim;
use App\Models\FriendActivityFeed;
use App\Models\AvatarItem;
use App\Models\UserAvatarItem;

class SocialShareController extends Controller
{
    /**
     * Create a social share
     */
    public function createShare(Request $request)
    {
        $validated = $request->validate([
            'share_type' => 'required|in:workout,nutrition,achievement,badge,progression,challenge',
            'shareable_id' => 'required|integer',
            'shareable_type' => 'required|string',
            'caption' => 'nullable|string|max:500',
            'platform' => 'required|in:internal,facebook,instagram,twitter,tiktok,linkedin'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Create the share
        $share = SocialShare::create([
            'user_id' => $user->id,
            'share_type' => $validated['share_type'],
            'shareable_id' => $validated['shareable_id'],
            'shareable_type' => $validated['shareable_type'],
            'caption' => $validated['caption'] ?? '',
            'platform' => $validated['platform']
        ]);

        // Create activity feed entry
        FriendActivityFeed::create([
            'user_id' => $user->id,
            'activity_type' => 'shared_' . $validated['share_type'],
            'activity_description' => ucfirst($validated['share_type']) . ' shared to ' . $validated['platform'],
            'activity_data' => [
                'share_id' => $share->id,
                'platform' => $validated['platform']
            ],
            'activity_icon' => 'share-social',
            'is_public' => true
        ]);

        // Process rewards
        $rewards = $this->processShareRewards($user->id, $validated['share_type'], $share->id);

        return response()->json([
            'success' => true,
            'message' => 'Content shared successfully',
            'share' => $share,
            'rewards' => $rewards
        ], 201);
    }

    /**
     * Process share rewards
     */
    private function processShareRewards($userId, $shareType, $shareId)
    {
        $rewards = [];

        // Check for first share reward
        $firstShareCount = SocialShare::where('user_id', $userId)->count();
        if ($firstShareCount == 1) {
            $firstShareReward = SocialShareReward::active()
                ->shareType('first_share')
                ->first();

            if ($firstShareReward && $firstShareReward->canClaim($userId)) {
                $claim = $firstShareReward->processClaim($userId, $shareId);
                $rewards[] = [
                    'type' => 'first_share',
                    'points' => $claim->points_earned,
                    'items' => $claim->items_earned
                ];
            }
        }

        // Check for type-specific reward
        $typeReward = SocialShareReward::active()
            ->shareType($shareType . '_share')
            ->first();

        if ($typeReward && $typeReward->canClaim($userId)) {
            $claim = $typeReward->processClaim($userId, $shareId);
            $rewards[] = [
                'type' => $shareType . '_share',
                'points' => $claim->points_earned,
                'items' => $claim->items_earned
            ];

            // Mark share as rewarded
            $share = SocialShare::find($shareId);
            if ($share) {
                $share->markAsRewarded($claim->items_earned);
            }
        }

        return $rewards;
    }

    /**
     * Get user's shares
     */
    public function getMyShares(Request $request)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $shares = SocialShare::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'shares' => $shares
        ]);
    }

    /**
     * Get shares by friends
     */
    public function getFriendsShares(Request $request)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        // Get friend IDs
        $friendIds = \App\Models\UserConnection::where(function($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('friend_id', $user->id);
        })
        ->where('status', 'accepted')
        ->get()
        ->map(function($connection) use ($user) {
            return $connection->user_id == $user->id ? $connection->friend_id : $connection->user_id;
        });

        $shares = SocialShare::whereIn('user_id', $friendIds)
            ->where('platform', 'internal')
            ->with('user:id,first_name,last_name,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'shares' => $shares
        ]);
    }

    /**
     * Like a share
     */
    public function likeShare($shareId)
    {
        $share = SocialShare::findOrFail($shareId);
        $share->incrementLikes();

        return response()->json([
            'success' => true,
            'message' => 'Share liked',
            'likes_count' => $share->likes_count
        ]);
    }

    /**
     * Get sharing stats
     */
    public function getShareStats()
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $stats = [
            'total_shares' => SocialShare::where('user_id', $user->id)->count(),
            'total_likes' => SocialShare::where('user_id', $user->id)->sum('likes_count'),
            'platforms' => SocialShare::where('user_id', $user->id)
                ->select('platform', \DB::raw('count(*) as count'))
                ->groupBy('platform')
                ->get(),
            'share_types' => SocialShare::where('user_id', $user->id)
                ->select('share_type', \DB::raw('count(*) as count'))
                ->groupBy('share_type')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Delete a share
     */
    public function deleteShare($shareId)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $share = SocialShare::where('id', $shareId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $share->delete();

        return response()->json([
            'success' => true,
            'message' => 'Share deleted successfully'
        ]);
    }
}
