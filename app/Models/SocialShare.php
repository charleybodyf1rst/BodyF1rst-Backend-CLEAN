<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'share_type',
        'shareable_id',
        'shareable_type',
        'caption',
        'platform',
        'external_post_id',
        'external_post_url',
        'likes_count',
        'comments_count',
        'shares_count',
        'rewarded',
        'reward_items'
    ];

    protected $casts = [
        'reward_items' => 'array',
        'rewarded' => 'boolean',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'shares_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user who created the share
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the shareable item (polymorphic)
     */
    public function shareable()
    {
        return $this->morphTo();
    }

    /**
     * Increment likes count
     */
    public function incrementLikes()
    {
        $this->increment('likes_count');
    }

    /**
     * Increment comments count
     */
    public function incrementComments()
    {
        $this->increment('comments_count');
    }

    /**
     * Increment shares count
     */
    public function incrementShares()
    {
        $this->increment('shares_count');
    }

    /**
     * Mark as rewarded
     */
    public function markAsRewarded($rewardItems)
    {
        $this->update([
            'rewarded' => true,
            'reward_items' => $rewardItems
        ]);
    }

    /**
     * Scope to filter by platform
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to filter by share type
     */
    public function scopeType($query, $type)
    {
        return $query->where('share_type', $type);
    }
}
