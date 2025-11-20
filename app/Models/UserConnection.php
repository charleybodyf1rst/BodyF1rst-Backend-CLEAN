<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'friend_id',
        'status',
        'connection_source',
        'can_view_progression',
        'can_message',
        'share_workouts',
        'share_nutrition',
        'share_achievements',
        'connected_at'
    ];

    protected $casts = [
        'can_view_progression' => 'boolean',
        'can_message' => 'boolean',
        'share_workouts' => 'boolean',
        'share_nutrition' => 'boolean',
        'share_achievements' => 'boolean',
        'connected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user who initiated the connection
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the friend in the connection
     */
    public function friend()
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    /**
     * Scope to get accepted connections only
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope to get pending connections
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get connections for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)->orWhere('friend_id', $userId);
    }

    /**
     * Accept a friend request
     */
    public function accept()
    {
        $this->update([
            'status' => 'accepted',
            'connected_at' => now()
        ]);
    }

    /**
     * Block a user
     */
    public function block()
    {
        $this->update(['status' => 'blocked']);
    }
}
