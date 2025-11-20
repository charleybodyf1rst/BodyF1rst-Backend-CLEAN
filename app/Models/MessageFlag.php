<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageFlag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'message_id',
        'flagged_by',
        'flagged_by_type',
        'flag_type',
        'reason',
        'status',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
        'metadata'
    ];

    protected $casts = [
        'message_id' => 'integer',
        'flagged_by' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the message that owns the flag.
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user who flagged the message.
     */
    public function flagger()
    {
        if ($this->flagged_by_type === 'user') {
            return $this->belongsTo(User::class, 'flagged_by');
        } elseif ($this->flagged_by_type === 'coach') {
            return $this->belongsTo(Coach::class, 'flagged_by');
        } elseif ($this->flagged_by_type === 'admin') {
            return $this->belongsTo(Admin::class, 'flagged_by');
        }
        return null;
    }

    /**
     * Get the admin who reviewed the flag.
     */
    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /**
     * Scope a query to only include pending flags.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include reviewed flags.
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Mark flag as reviewed
     */
    public function markAsReviewed($adminId, $notes = null)
    {
        $this->update([
            'status' => 'reviewed',
            'reviewed_by' => $adminId,
            'review_notes' => $notes,
            'reviewed_at' => now()
        ]);
    }
}
