<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'blocker_id',
        'blocker_type',
        'blocked_id',
        'blocked_type',
        'reason'
    ];

    protected $casts = [
        'blocker_id' => 'integer',
        'blocked_id' => 'integer',
    ];

    /**
     * Get the user who blocked.
     */
    public function blocker()
    {
        if ($this->blocker_type === 'user') {
            return $this->belongsTo(User::class, 'blocker_id');
        } elseif ($this->blocker_type === 'coach') {
            return $this->belongsTo(Coach::class, 'blocker_id');
        } elseif ($this->blocker_type === 'admin') {
            return $this->belongsTo(Admin::class, 'blocker_id');
        }
        return null;
    }

    /**
     * Get the user who was blocked.
     */
    public function blocked()
    {
        if ($this->blocked_type === 'user') {
            return $this->belongsTo(User::class, 'blocked_id');
        } elseif ($this->blocked_type === 'coach') {
            return $this->belongsTo(Coach::class, 'blocked_id');
        } elseif ($this->blocked_type === 'admin') {
            return $this->belongsTo(Admin::class, 'blocked_id');
        }
        return null;
    }

    /**
     * Check if user is blocked
     */
    public static function isBlocked($blockerId, $blockerType, $blockedId, $blockedType)
    {
        return self::where('blocker_id', $blockerId)
            ->where('blocker_type', $blockerType)
            ->where('blocked_id', $blockedId)
            ->where('blocked_type', $blockedType)
            ->exists();
    }
}
