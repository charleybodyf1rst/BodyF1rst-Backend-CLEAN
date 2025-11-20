<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRewardClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'social_share_reward_id',
        'social_share_id',
        'points_earned',
        'items_earned',
        'claimed_at'
    ];

    protected $casts = [
        'items_earned' => 'array',
        'points_earned' => 'integer',
        'claimed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user who claimed the reward
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reward that was claimed
     */
    public function reward()
    {
        return $this->belongsTo(SocialShareReward::class, 'social_share_reward_id');
    }

    /**
     * Get the social share that triggered this reward
     */
    public function socialShare()
    {
        return $this->belongsTo(SocialShare::class);
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent claims
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('claimed_at', '>=', now()->subDays($days));
    }
}
