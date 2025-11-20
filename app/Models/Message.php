<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'reply_to_message_id',
        'message',
        'message_encrypted',
        'attachments',
        'message_type',
        'is_edited',
        'is_deleted',
        'is_pinned',
        'is_forwarded',
        'is_scheduled',
        'scheduled_at',
        'delivered_at',
        'edited_at'
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'sender_id' => 'integer',
        'reply_to_message_id' => 'integer',
        'attachments' => 'array',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
        'is_pinned' => 'boolean',
        'is_forwarded' => 'boolean',
        'is_scheduled' => 'boolean',
        'scheduled_at' => 'datetime',
        'delivered_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    protected $appends = ['read_count', 'reaction_summary'];

    /**
     * Get the conversation that owns the message.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender of the message.
     */
    public function sender()
    {
        if ($this->sender_type === 'user') {
            return $this->belongsTo(User::class, 'sender_id');
        } elseif ($this->sender_type === 'coach') {
            return $this->belongsTo(Coach::class, 'sender_id');
        } elseif ($this->sender_type === 'admin') {
            return $this->belongsTo(Admin::class, 'sender_id');
        }
        return null;
    }

    /**
     * Get the message this message is replying to.
     */
    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    /**
     * Get the replies for this message.
     */
    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_message_id');
    }

    /**
     * Get the reactions for the message.
     */
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * Get the read receipts for the message.
     */
    public function reads()
    {
        return $this->hasMany(MessageRead::class);
    }

    /**
     * Get the flags for the message.
     */
    public function flags()
    {
        return $this->hasMany(MessageFlag::class);
    }

    /**
     * Get the edit history for the message.
     */
    public function editHistory()
    {
        return $this->hasMany(MessageEditHistory::class)->orderBy('edited_at', 'desc');
    }

    /**
     * Get read count attribute
     */
    public function getReadCountAttribute()
    {
        return $this->reads()->count();
    }

    /**
     * Get reaction summary attribute
     */
    public function getReactionSummaryAttribute()
    {
        return $this->reactions()
            ->selectRaw('reaction, COUNT(*) as count')
            ->groupBy('reaction')
            ->get()
            ->pluck('count', 'reaction')
            ->toArray();
    }

    /**
     * Check if message is read by user
     */
    public function isReadBy($userId, $userType = 'user')
    {
        return $this->reads()
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->exists();
    }

    /**
     * Mark message as read by user
     */
    public function markAsReadBy($userId, $userType = 'user')
    {
        return $this->reads()->updateOrCreate(
            [
                'user_id' => $userId,
                'user_type' => $userType
            ],
            [
                'read_at' => now()
            ]
        );
    }

    /**
     * Add reaction to message
     */
    public function addReaction($userId, $userType, $reaction)
    {
        return $this->reactions()->updateOrCreate(
            [
                'user_id' => $userId,
                'user_type' => $userType,
                'reaction' => $reaction
            ]
        );
    }

    /**
     * Remove reaction from message
     */
    public function removeReaction($userId, $userType, $reaction)
    {
        return $this->reactions()
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('reaction', $reaction)
            ->delete();
    }

    /**
     * Flag message
     */
    public function flagMessage($flagType, $reason = null, $flaggedBy = null, $flaggedByType = 'system', $metadata = null)
    {
        return $this->flags()->create([
            'flagged_by' => $flaggedBy,
            'flagged_by_type' => $flaggedByType,
            'flag_type' => $flagType,
            'reason' => $reason,
            'metadata' => $metadata,
            'status' => 'pending'
        ]);
    }
}
