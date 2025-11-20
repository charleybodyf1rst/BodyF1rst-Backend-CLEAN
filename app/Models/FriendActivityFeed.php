<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FriendActivityFeed extends Model
{
    use HasFactory;

    protected $table = 'friend_activity_feed';

    protected $fillable = [
        'user_id',
        'activity_type',
        'activity_description',
        'activity_data',
        'activity_icon',
        'activity_image_url',
        'is_public',
        'likes_count',
        'comments_count'
    ];

    protected $casts = [
        'activity_data' => 'array',
        'is_public' => 'boolean',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user who performed the activity
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get public activities only
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to get recent activities
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get activity feed for a user's friends
     */
    public static function getFriendsFeed($userId, $limit = 20)
    {
        // Get user's accepted friend IDs
        $friendIds = UserConnection::where(function($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('friend_id', $userId);
        })
        ->where('status', 'accepted')
        ->get()
        ->map(function($connection) use ($userId) {
            return $connection->user_id == $userId ? $connection->friend_id : $connection->user_id;
        });

        return self::whereIn('user_id', $friendIds)
            ->where('is_public', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->with('user:id,first_name,last_name,avatar')
            ->get();
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
}
