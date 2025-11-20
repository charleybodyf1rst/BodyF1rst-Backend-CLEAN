<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialShareReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'share_type',
        'points_reward',
        'avatar_items_reward',
        'max_claims_per_user',
        'is_active'
    ];

    protected $casts = [
        'avatar_items_reward' => 'array',
        'is_active' => 'boolean',
        'points_reward' => 'integer',
        'max_claims_per_user' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get reward claims for this reward
     */
    public function claims()
    {
        return $this->hasMany(UserRewardClaim::class);
    }

    /**
     * Check if user can claim this reward
     */
    public function canClaim($userId)
    {
        if (!$this->is_active) {
            return false;
        }

        $claimsCount = $this->claims()
            ->where('user_id', $userId)
            ->count();

        return $claimsCount < $this->max_claims_per_user;
    }

    /**
     * Process reward claim for a user
     */
    public function processClaim($userId, $socialShareId = null)
    {
        if (!$this->canClaim($userId)) {
            return null;
        }

        $user = User::find($userId);

        // Award points
        if ($this->points_reward > 0) {
            $user->increment('points', $this->points_reward);
        }

        // Award avatar items
        $itemsEarned = [];
        if ($this->avatar_items_reward && count($this->avatar_items_reward) > 0) {
            foreach ($this->avatar_items_reward as $avatarItemId) {
                // Check if user already has this item
                if (!$user->avatarItems()->where('avatar_item_id', $avatarItemId)->exists()) {
                    UserAvatarItem::create([
                        'user_id' => $userId,
                        'avatar_item_id' => $avatarItemId,
                        'unlocked_at' => now(),
                        'unlocked_via' => 'social_share_reward'
                    ]);
                    $itemsEarned[] = $avatarItemId;
                }
            }
        }

        // Create claim record
        $claim = UserRewardClaim::create([
            'user_id' => $userId,
            'social_share_reward_id' => $this->id,
            'social_share_id' => $socialShareId,
            'points_earned' => $this->points_reward,
            'items_earned' => $itemsEarned,
            'claimed_at' => now()
        ]);

        return $claim;
    }

    /**
     * Scope to get active rewards
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by share type
     */
    public function scopeShareType($query, $type)
    {
        return $query->where('share_type', $type);
    }
}
