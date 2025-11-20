<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\AvatarItem;
use App\Models\UserAvatarItem;
use App\Models\User;

class AvatarController extends Controller
{
    /**
     * Get avatar item catalog
     */
    public function getCatalog(Request $request)
    {
        $query = AvatarItem::active();

        // Filter by type
        if ($request->has('item_type')) {
            $query->type($request->item_type);
        }

        // Filter by rarity
        if ($request->has('rarity')) {
            $query->rarity($request->rarity);
        }

        // Filter by unlock method
        if ($request->has('unlock_method')) {
            $query->unlockMethod($request->unlock_method);
        }

        $items = $query->orderBy('rarity', 'desc')
            ->orderBy('name')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'items' => $items
        ]);
    }

    /**
     * Get user's avatar items (inventory)
     */
    public function getMyItems(Request $request)
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $items = UserAvatarItem::where('user_id', $user->id)
            ->with('avatarItem')
            ->orderBy('unlocked_at', 'desc')
            ->get();

        $equipped = $items->where('is_equipped', true)->groupBy(function($item) {
            return $item->avatarItem->item_type;
        });

        return response()->json([
            'success' => true,
            'inventory' => $items,
            'equipped_items' => $equipped,
            'total_items' => $items->count()
        ]);
    }

    /**
     * Unlock an avatar item
     */
    public function unlockItem(Request $request)
    {
        $validated = $request->validate([
            'avatar_item_id' => 'required|exists:avatar_items,id'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();
        $item = AvatarItem::findOrFail($validated['avatar_item_id']);

        // Check if already owned
        if (UserAvatarItem::where('user_id', $user->id)
            ->where('avatar_item_id', $item->id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already own this item'
            ], 400);
        }

        // Check if can unlock
        if (!$item->canUnlock($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot unlock this item. Check unlock requirements.'
            ], 400);
        }

        // Deduct points if needed
        if ($item->unlock_method === 'points') {
            $user->decrement('points', $item->unlock_cost);
        }

        // Create user avatar item
        $userItem = UserAvatarItem::create([
            'user_id' => $user->id,
            'avatar_item_id' => $item->id,
            'unlocked_at' => now(),
            'unlocked_via' => $item->unlock_method
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item unlocked successfully',
            'item' => $userItem->load('avatarItem')
        ], 201);
    }

    /**
     * Equip an avatar item
     */
    public function equipItem(Request $request)
    {
        $validated = $request->validate([
            'user_avatar_item_id' => 'required|exists:user_avatar_items,id'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        $userItem = UserAvatarItem::where('id', $validated['user_avatar_item_id'])
            ->where('user_id', $user->id)
            ->with('avatarItem')
            ->firstOrFail();

        $userItem->equip();

        return response()->json([
            'success' => true,
            'message' => 'Item equipped successfully',
            'equipped_item' => $userItem
        ]);
    }

    /**
     * Unequip an avatar item
     */
    public function unequipItem(Request $request)
    {
        $validated = $request->validate([
            'user_avatar_item_id' => 'required|exists:user_avatar_items,id'
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        $userItem = UserAvatarItem::where('id', $validated['user_avatar_item_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $userItem->unequip();

        return response()->json([
            'success' => true,
            'message' => 'Item unequipped successfully'
        ]);
    }

    /**
     * Get user's currently equipped avatar (for 3D display)
     */
    public function getEquippedAvatar(Request $request, $userId = null)
    {
        if (!$userId) {
            $user = Auth::guard('admin')->user() ?? Auth::user();
            $userId = $user->id;
        }

        $equippedItems = UserAvatarItem::where('user_id', $userId)
            ->where('is_equipped', true)
            ->with('avatarItem')
            ->get()
            ->groupBy(function($item) {
                return $item->avatarItem->item_type;
            })
            ->map(function($items) {
                return $items->first()->avatarItem;
            });

        return response()->json([
            'success' => true,
            'equipped_avatar' => $equippedItems,
            'user_id' => $userId
        ]);
    }

    /**
     * Admin: Create a new avatar item
     */
    public function adminCreateItem(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'item_type' => 'required|in:clothing,accessory,hairstyle,background,effect,badge,emote,skin',
            'rarity' => 'required|in:common,uncommon,rare,epic,legendary',
            'thumbnail_url' => 'nullable|string',
            'asset_url' => 'nullable|string',
            'unlock_cost' => 'integer|min:0',
            'unlock_method' => 'required|in:points,achievement,social_share,purchase,free',
            'is_premium' => 'boolean',
            'unlock_requirements' => 'nullable|array'
        ]);

        $item = AvatarItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Avatar item created successfully',
            'item' => $item
        ], 201);
    }

    /**
     * Admin: Update avatar item
     */
    public function adminUpdateItem(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'item_type' => 'in:clothing,accessory,hairstyle,background,effect,badge,emote,skin',
            'rarity' => 'in:common,uncommon,rare,epic,legendary',
            'thumbnail_url' => 'nullable|string',
            'asset_url' => 'nullable|string',
            'unlock_cost' => 'integer|min:0',
            'unlock_method' => 'in:points,achievement,social_share,purchase,free',
            'is_premium' => 'boolean',
            'unlock_requirements' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        $item = AvatarItem::findOrFail($id);
        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Avatar item updated successfully',
            'item' => $item
        ]);
    }

    /**
     * Admin: Delete avatar item
     */
    public function adminDeleteItem($id)
    {
        $item = AvatarItem::findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Avatar item deleted successfully'
        ]);
    }

    /**
     * Get avatar stats
     */
    public function getAvatarStats()
    {
        $user = Auth::guard('admin')->user() ?? Auth::user();

        $stats = [
            'total_items_owned' => UserAvatarItem::where('user_id', $user->id)->count(),
            'items_equipped' => UserAvatarItem::where('user_id', $user->id)->where('is_equipped', true)->count(),
            'total_items_available' => AvatarItem::active()->count(),
            'completion_percentage' => 0,
            'items_by_rarity' => UserAvatarItem::where('user_id', $user->id)
                ->join('avatar_items', 'user_avatar_items.avatar_item_id', '=', 'avatar_items.id')
                ->select('avatar_items.rarity', \DB::raw('count(*) as count'))
                ->groupBy('avatar_items.rarity')
                ->get()
        ];

        $totalAvailable = AvatarItem::active()->count();
        if ($totalAvailable > 0) {
            $stats['completion_percentage'] = round(($stats['total_items_owned'] / $totalAvailable) * 100, 2);
        }

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Create 3D Avatar Configuration
     * POST /api/avatar/create-3d-avatar
     */
    public function create3DAvatar(Request $request)
    {
        $validated = $request->validate([
            'bodyType' => 'required|string|in:male,female,custom',
            'height' => 'nullable|numeric|min:100|max:250',
            'weight' => 'nullable|numeric|min:30|max:300',
            'bodyComposition' => 'nullable|array',
            'bodyComposition.bodyFat' => 'nullable|numeric|min:0|max:100',
            'bodyComposition.muscleMass' => 'nullable|numeric|min:0|max:100',
            'skinTone' => 'nullable|string',
            'baseModel' => 'nullable|string',
            'customizations' => 'nullable|array',
            'customizations.*.type' => 'nullable|string',
            'customizations.*.value' => 'nullable',
            'equippedItems' => 'nullable|array',
            'equippedItems.*' => 'integer|exists:user_avatar_items,id',
            'animations' => 'nullable|array',
            'animations.*' => 'string',
            'pose' => 'nullable|string',
        ]);

        try {
            $user = Auth::guard('admin')->user() ?? Auth::user();

            // Build 3D avatar configuration
            $avatarConfig = [
                'user_id' => $user->id,
                'body_type' => $validated['bodyType'] ?? 'custom',
                'height' => $validated['height'] ?? $user->height ?? 170,
                'weight' => $validated['weight'] ?? $user->weight ?? 70,
                'body_composition' => json_encode($validated['bodyComposition'] ?? [
                    'bodyFat' => $user->body_fat_percentage ?? 20,
                    'muscleMass' => $user->muscle_mass ?? 30,
                    'leanMass' => 50,
                ]),
                'skin_tone' => $validated['skinTone'] ?? '#f5d1b8',
                'base_model' => $validated['baseModel'] ?? 'default',
                'customizations' => json_encode($validated['customizations'] ?? []),
                'equipped_items' => json_encode($validated['equippedItems'] ?? []),
                'animations' => json_encode($validated['animations'] ?? ['idle', 'walk', 'run']),
                'pose' => $validated['pose'] ?? 'idle',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Store avatar configuration in user's profile or separate table
            $user->avatar_config = json_encode($avatarConfig);
            $user->save();

            // Get equipped items details
            $equippedItemsDetails = [];
            if (!empty($validated['equippedItems'])) {
                $equippedItemsDetails = UserAvatarItem::whereIn('id', $validated['equippedItems'])
                    ->where('user_id', $user->id)
                    ->with('avatarItem')
                    ->get()
                    ->map(function($item) {
                        return [
                            'id' => $item->id,
                            'itemType' => $item->avatarItem->item_type,
                            'name' => $item->avatarItem->name,
                            'assetUrl' => $item->avatarItem->asset_url,
                            'thumbnailUrl' => $item->avatarItem->thumbnail_url,
                        ];
                    });
            }

            return response()->json([
                'success' => true,
                'message' => '3D Avatar created successfully',
                'avatar' => [
                    'userId' => $user->id,
                    'bodyType' => $avatarConfig['body_type'],
                    'height' => $avatarConfig['height'],
                    'weight' => $avatarConfig['weight'],
                    'bodyComposition' => json_decode($avatarConfig['body_composition']),
                    'skinTone' => $avatarConfig['skin_tone'],
                    'baseModel' => $avatarConfig['base_model'],
                    'customizations' => json_decode($avatarConfig['customizations']),
                    'equippedItems' => $equippedItemsDetails,
                    'animations' => json_decode($avatarConfig['animations']),
                    'pose' => $avatarConfig['pose'],
                    'renderUrl' => route('avatar.render', $user->id),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create 3D avatar',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update 3D Avatar Configuration
     * PUT /api/avatar/update-3d-avatar
     */
    public function update3DAvatar(Request $request)
    {
        $validated = $request->validate([
            'bodyType' => 'nullable|string|in:male,female,custom',
            'height' => 'nullable|numeric|min:100|max:250',
            'weight' => 'nullable|numeric|min:30|max:300',
            'bodyComposition' => 'nullable|array',
            'bodyComposition.bodyFat' => 'nullable|numeric|min:0|max:100',
            'bodyComposition.muscleMass' => 'nullable|numeric|min:0|max:100',
            'skinTone' => 'nullable|string',
            'baseModel' => 'nullable|string',
            'customizations' => 'nullable|array',
            'equippedItems' => 'nullable|array',
            'equippedItems.*' => 'integer|exists:user_avatar_items,id',
            'animations' => 'nullable|array',
            'animations.*' => 'string',
            'pose' => 'nullable|string',
        ]);

        try {
            $user = Auth::guard('admin')->user() ?? Auth::user();

            // Get existing avatar config
            $existingConfig = $user->avatar_config ? json_decode($user->avatar_config, true) : [];

            // Merge with new values
            $avatarConfig = array_merge($existingConfig, [
                'body_type' => $validated['bodyType'] ?? $existingConfig['body_type'] ?? 'custom',
                'height' => $validated['height'] ?? $existingConfig['height'] ?? $user->height ?? 170,
                'weight' => $validated['weight'] ?? $existingConfig['weight'] ?? $user->weight ?? 70,
                'body_composition' => isset($validated['bodyComposition'])
                    ? json_encode($validated['bodyComposition'])
                    : ($existingConfig['body_composition'] ?? json_encode(['bodyFat' => 20, 'muscleMass' => 30])),
                'skin_tone' => $validated['skinTone'] ?? $existingConfig['skin_tone'] ?? '#f5d1b8',
                'base_model' => $validated['baseModel'] ?? $existingConfig['base_model'] ?? 'default',
                'customizations' => isset($validated['customizations'])
                    ? json_encode($validated['customizations'])
                    : ($existingConfig['customizations'] ?? json_encode([])),
                'equipped_items' => isset($validated['equippedItems'])
                    ? json_encode($validated['equippedItems'])
                    : ($existingConfig['equipped_items'] ?? json_encode([])),
                'animations' => isset($validated['animations'])
                    ? json_encode($validated['animations'])
                    : ($existingConfig['animations'] ?? json_encode(['idle', 'walk', 'run'])),
                'pose' => $validated['pose'] ?? $existingConfig['pose'] ?? 'idle',
                'updated_at' => now(),
            ]);

            // Update avatar configuration
            $user->avatar_config = json_encode($avatarConfig);
            $user->save();

            // Get equipped items details
            $equippedItemsIds = json_decode($avatarConfig['equipped_items'], true) ?? [];
            $equippedItemsDetails = [];
            if (!empty($equippedItemsIds)) {
                $equippedItemsDetails = UserAvatarItem::whereIn('id', $equippedItemsIds)
                    ->where('user_id', $user->id)
                    ->with('avatarItem')
                    ->get()
                    ->map(function($item) {
                        return [
                            'id' => $item->id,
                            'itemType' => $item->avatarItem->item_type,
                            'name' => $item->avatarItem->name,
                            'assetUrl' => $item->avatarItem->asset_url,
                            'thumbnailUrl' => $item->avatarItem->thumbnail_url,
                        ];
                    });
            }

            return response()->json([
                'success' => true,
                'message' => '3D Avatar updated successfully',
                'avatar' => [
                    'userId' => $user->id,
                    'bodyType' => $avatarConfig['body_type'],
                    'height' => $avatarConfig['height'],
                    'weight' => $avatarConfig['weight'],
                    'bodyComposition' => json_decode($avatarConfig['body_composition']),
                    'skinTone' => $avatarConfig['skin_tone'],
                    'baseModel' => $avatarConfig['base_model'],
                    'customizations' => json_decode($avatarConfig['customizations']),
                    'equippedItems' => $equippedItemsDetails,
                    'animations' => json_decode($avatarConfig['animations']),
                    'pose' => $avatarConfig['pose'],
                    'renderUrl' => route('avatar.render', $user->id),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update 3D avatar',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Claim social sharing reward
     * POST /api/avatar/share/reward/claim
     */
    public function claimSocialShareReward(Request $request)
    {
        try {
            $user = Auth::guard('admin')->user() ?? Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $request->validate([
                'share_type' => 'required|in:first_share,workout_share,nutrition_share,achievement_share,badge_share,progression_share',
                'social_share_id' => 'nullable|integer',
            ]);

            // Get the reward for this share type
            $reward = \DB::table('social_share_rewards')
                ->where('share_type', $request->share_type)
                ->where('is_active', true)
                ->first();

            if (!$reward) {
                return response()->json([
                    'success' => false,
                    'message' => 'No reward available for this share type'
                ], 404);
            }

            // Check if user has already claimed this reward max times
            $claimCount = \DB::table('user_reward_claims')
                ->where('user_id', $user->id)
                ->where('social_share_reward_id', $reward->id)
                ->count();

            if ($claimCount >= $reward->max_claims_per_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum claims reached for this reward'
                ], 400);
            }

            \DB::beginTransaction();

            // Create the claim record
            $claimId = \DB::table('user_reward_claims')->insertGetId([
                'user_id' => $user->id,
                'social_share_reward_id' => $reward->id,
                'social_share_id' => $request->social_share_id,
                'points_earned' => $reward->points_reward,
                'items_earned' => $reward->avatar_items_reward,
                'claimed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Award points
            if ($reward->points_reward > 0) {
                \DB::table('users')
                    ->where('id', $user->id)
                    ->increment('avatar_points', $reward->points_reward);
            }

            // Award avatar items
            if ($reward->avatar_items_reward) {
                $itemIds = json_decode($reward->avatar_items_reward, true);
                foreach ($itemIds as $itemId) {
                    \DB::table('user_avatar_items')->updateOrInsert(
                        [
                            'user_id' => $user->id,
                            'avatar_item_id' => $itemId,
                        ],
                        [
                            'acquired_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reward claimed successfully',
                'data' => [
                    'points_earned' => $reward->points_reward,
                    'items_earned' => json_decode($reward->avatar_items_reward),
                    'claim_id' => $claimId,
                ]
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to claim reward',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get social sharing stats
     * GET /api/avatar/share/stats
     */
    public function getSocialShareStats(Request $request)
    {
        try {
            $user = Auth::guard('admin')->user() ?? Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Get all available rewards
            $availableRewards = \DB::table('social_share_rewards')
                ->where('is_active', true)
                ->get();

            // Get user's claims
            $userClaims = \DB::table('user_reward_claims')
                ->where('user_id', $user->id)
                ->select('social_share_reward_id', \DB::raw('COUNT(*) as claim_count'), \DB::raw('SUM(points_earned) as total_points'))
                ->groupBy('social_share_reward_id')
                ->get()
                ->keyBy('social_share_reward_id');

            // Build stats for each reward type
            $rewardStats = $availableRewards->map(function($reward) use ($userClaims) {
                $claimed = $userClaims->get($reward->id);
                $claimCount = $claimed ? $claimed->claim_count : 0;

                return [
                    'share_type' => $reward->share_type,
                    'points_reward' => $reward->points_reward,
                    'avatar_items_reward' => json_decode($reward->avatar_items_reward),
                    'max_claims' => $reward->max_claims_per_user,
                    'claims_used' => $claimCount,
                    'claims_remaining' => max(0, $reward->max_claims_per_user - $claimCount),
                    'can_claim' => $claimCount < $reward->max_claims_per_user,
                ];
            });

            // Get total stats
            $totalPoints = \DB::table('user_reward_claims')
                ->where('user_id', $user->id)
                ->sum('points_earned');

            $totalClaims = \DB::table('user_reward_claims')
                ->where('user_id', $user->id)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_points_earned' => $totalPoints ?? 0,
                    'total_claims' => $totalClaims,
                    'rewards' => $rewardStats,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get share stats',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
