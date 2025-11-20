<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationParticipant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'participant_id',
        'participant_type',
        'is_admin',
        'is_muted',
        'last_read_at',
        'joined_at',
        'left_at'
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'participant_id' => 'integer',
        'is_admin' => 'boolean',
        'is_muted' => 'boolean',
        'last_read_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    /**
     * Get the conversation that owns the participant.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the participant user.
     */
    public function participant()
    {
        if ($this->participant_type === 'user') {
            return $this->belongsTo(User::class, 'participant_id');
        } elseif ($this->participant_type === 'coach') {
            return $this->belongsTo(Coach::class, 'participant_id');
        } elseif ($this->participant_type === 'admin') {
            return $this->belongsTo(Admin::class, 'participant_id');
        }
        return null;
    }

    /**
     * Mark conversation as read
     */
    public function markAsRead()
    {
        $this->update(['last_read_at' => now()]);
    }
}
