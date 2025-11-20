<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AvatarItem;
use App\Models\UserAvatarItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Enhanced Avatar & Gamification Controller
 * Extends avatar customization and gamification with BodyPoints economy
 */
class AvatarGamificationEnhancedController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get Avatar Item Catalog with filtering and pagination
     * GET /api/avatar/catalog
     */
    public function getCatalog(Request $request)
    {
        try {
            $userId = Auth::id();
            $user = User::find($userId);

            $query = AvatarItem::where('is_active', true);

            // Filters
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('rarity')) {
                $query->where('rarity', $request->rarity);
            }

            if ($request->has('unlock_method')) {
                $query->where('unlock_method', $request->unlock_method);
            }

            // Price range
            if ($request->has('min_price')) {
                $query->where('price_body_points', '>=', $request->min_price);
            }

            if ($request->has('max_price')) {
                $query->where('price_body_points', '<=', $request->max_price);
            }

            // Get owned item IDs for this user
            $ownedItemIds = UserAvatarItem::where('user_id', $userId)
                ->pluck('avatar_item_id')
                ->toArray();

            $items = $query->orderBy('category')
                ->orderBy('rarity', 'desc')
                ->orderBy('price_body_points')
                ->get()
                ->map(function ($item) use ($ownedItemIds, $user) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'category' => $item->category, // head, body, accessory, background, etc.
                        'rarity' => $item->rarity, // common, rare, epic, legendary
                        'price_body_points' => $item->price_body_points,
                        'unlock_method' => $item->unlock_method, // purchase, achievement, level, event
                        'unlock_requirement' => $item->unlock_requirement,
                        'image_url' => $item->image_url,
                        'preview_url' => $item->preview_url,
                        'is_owned' => in_array($item->id, $ownedItemIds),
                        'can_afford' => $user->body_points >= $item->price_body_points,
                        'is_unlocked' => $this->checkUnlockRequirement($user, $item),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $items,
                    'userBodyPoints' => $user->body_points ?? 0,
                    'totalItems' => $items->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load avatar catalog',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get User's Avatar Inventory
     * GET /api/avatar/inventory
     */
    public function getInventory(Request $request)
    {
        try {
            $userId = Auth::id();

            $inventory = UserAvatarItem::where('user_id', $userId)
                ->with('avatarItem')
                ->orderBy('acquired_at', 'desc')
                ->get()
                ->map(function ($userItem) {
                    $item = $userItem->avatarItem;
                    return [
                        'id' => $userItem->id,
                        'itemId' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'category' => $item->category,
                        'rarity' => $item->rarity,
                        'image_url' => $item->image_url,
                        'is_equipped' => $userItem->is_equipped ?? false,
                        'acquired_at' => $userItem->acquired_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'inventory' => $inventory,
                    'totalItems' => $inventory->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load inventory',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Currently Equipped Items
     * GET /api/avatar/equipped
     */
    public function getEquipped(Request $request)
    {
        try {
            $userId = Auth::id();

            $equipped = UserAvatarItem::where('user_id', $userId)
                ->where('is_equipped', true)
                ->with('avatarItem')
                ->get()
                ->map(function ($userItem) {
                    $item = $userItem->avatarItem;
                    return [
                        'category' => $item->category,
                        'itemId' => $item->id,
                        'name' => $item->name,
                        'image_url' => $item->image_url,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'equipped' => $equipped,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load equipped items',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Purchase Avatar Item
     * POST /api/avatar/purchase
     */
    public function purchaseItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:avatar_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = Auth::id();
            $user = User::find($userId);
            $item = AvatarItem::find($request->item_id);

            // Check if already owned
            $alreadyOwned = UserAvatarItem::where('user_id', $userId)
                ->where('avatar_item_id', $item->id)
                ->exists();

            if ($alreadyOwned) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already own this item',
                ], 400);
            }

            // Check unlock requirements
            if (!$this->checkUnlockRequirement($user, $item)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not meet the unlock requirements for this item',
                ], 400);
            }

            // Check if user has enough BodyPoints
            if ($user->body_points < $item->price_body_points) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient BodyPoints',
                    'required' => $item->price_body_points,
                    'current' => $user->body_points,
                ], 400);
            }

            // Process purchase
            DB::transaction(function () use ($user, $item, $userId) {
                // Deduct BodyPoints
                $user->decrement('body_points', $item->price_body_points);

                // Add to inventory
                UserAvatarItem::create([
                    'user_id' => $userId,
                    'avatar_item_id' => $item->id,
                    'acquired_at' => now(),
                    'acquired_via' => 'purchase',
                    'is_equipped' => false,
                ]);

                // Log transaction
                DB::table('body_points_transactions')->insert([
                    'user_id' => $userId,
                    'amount' => -$item->price_body_points,
                    'type' => 'avatar_purchase',
                    'description' => "Purchased avatar item: {$item->name}",
                    'reference_type' => 'avatar_item',
                    'reference_id' => $item->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Item purchased successfully',
                'data' => [
                    'item' => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'category' => $item->category,
                    ],
                    'newBodyPoints' => $user->body_points,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase item',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Equip Avatar Item
     * POST /api/avatar/equip
     */
    public function equipItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_item_id' => 'required|exists:user_avatar_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = Auth::id();

            $userItem = UserAvatarItem::where('id', $request->user_item_id)
                ->where('user_id', $userId)
                ->with('avatarItem')
                ->first();

            if (!$userItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in your inventory',
                ], 404);
            }

            $category = $userItem->avatarItem->category;

            // Unequip any currently equipped item in the same category
            UserAvatarItem::where('user_id', $userId)
                ->whereHas('avatarItem', function ($query) use ($category) {
                    $query->where('category', $category);
                })
                ->update(['is_equipped' => false]);

            // Equip the new item
            $userItem->update(['is_equipped' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Item equipped successfully',
                'data' => [
                    'itemId' => $userItem->avatarItem->id,
                    'category' => $category,
                    'name' => $userItem->avatarItem->name,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to equip item',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Unequip Avatar Item
     * POST /api/avatar/unequip
     */
    public function unequipItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = Auth::id();

            UserAvatarItem::where('user_id', $userId)
                ->where('is_equipped', true)
                ->whereHas('avatarItem', function ($query) use ($request) {
                    $query->where('category', $request->category);
                })
                ->update(['is_equipped' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Item unequipped successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unequip item',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get BodyPoints Balance and Transaction History
     * GET /api/gamification/body-points
     */
    public function getBodyPoints(Request $request)
    {
        try {
            $userId = Auth::id();
            $user = User::find($userId);

            $transactions = DB::table('body_points_transactions')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            $stats = [
                'current_balance' => $user->body_points ?? 0,
                'lifetime_earned' => DB::table('body_points_transactions')
                    ->where('user_id', $userId)
                    ->where('amount', '>', 0)
                    ->sum('amount'),
                'lifetime_spent' => abs(DB::table('body_points_transactions')
                    ->where('user_id', $userId)
                    ->where('amount', '<', 0)
                    ->sum('amount')),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => $user->body_points ?? 0,
                    'stats' => $stats,
                    'recent_transactions' => $transactions,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load BodyPoints data',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Award BodyPoints (internal use or admin)
     * POST /api/gamification/award-points
     */
    public function awardBodyPoints(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::find($request->user_id);

            DB::transaction(function () use ($user, $request) {
                // Add points to user
                $user->increment('body_points', $request->amount);

                // Log transaction
                DB::table('body_points_transactions')->insert([
                    'user_id' => $request->user_id,
                    'amount' => $request->amount,
                    'type' => $request->reference_type ?? 'manual_award',
                    'description' => $request->reason,
                    'reference_type' => $request->reference_type,
                    'reference_id' => $request->reference_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'BodyPoints awarded successfully',
                'data' => [
                    'new_balance' => $user->body_points,
                    'awarded' => $request->amount,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to award BodyPoints',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Helper Methods

    protected function checkUnlockRequirement($user, $item)
    {
        if (!$item->unlock_requirement) {
            return true; // No special requirement
        }

        $requirement = json_decode($item->unlock_requirement, true);

        return match($item->unlock_method) {
            'level' => $user->level >= ($requirement['level'] ?? 1),
            'achievement' => $this->hasAchievement($user->id, $requirement['achievement_id'] ?? null),
            'streak' => $user->current_streak >= ($requirement['streak'] ?? 1),
            'purchase', 'default' => true,
            default => true,
        };
    }

    protected function hasAchievement($userId, $achievementId)
    {
        if (!$achievementId) return false;

        return DB::table('user_achievements')
            ->where('user_id', $userId)
            ->where('achievement_id', $achievementId)
            ->exists();
    }
}
