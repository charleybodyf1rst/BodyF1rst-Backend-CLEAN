<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'description',
        'avatar',
        'organization_id',
        'created_by',
        'is_archived',
        'is_muted',
        'last_message_at'
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'created_by' => 'integer',
        'is_archived' => 'boolean',
        'is_muted' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    protected $appends = ['unread_count'];

    /**
     * Get the organization that owns the conversation.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who created the conversation.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the participants for the conversation.
     */
    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get the messages for the conversation.
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the last message for the conversation.
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Get the pinned messages for the conversation.
     */
    public function pinnedMessages()
    {
        return $this->hasMany(Message::class)->where('is_pinned', true)->orderBy('created_at', 'desc');
    }

    /**
     * Get unread count for current user
     */
    public function getUnreadCountAttribute()
    {
        $user = auth()->user();
        if (!$user) {
            return 0;
        }

        $participant = $this->participants()
            ->where('participant_id', $user->id)
            ->where('participant_type', get_class($user) === User::class ? 'user' : 'coach')
            ->first();

        if (!$participant) {
            return 0;
        }

        return $this->messages()
            ->where('created_at', '>', $participant->last_read_at ?? '1970-01-01')
            ->where('sender_id', '!=', $user->id)
            ->count();
    }

    /**
     * Check if user is participant
     */
    public function hasParticipant($userId, $userType = 'user')
    {
        return $this->participants()
            ->where('participant_id', $userId)
            ->where('participant_type', $userType)
            ->exists();
    }

    /**
     * Add participant to conversation
     */
    public function addParticipant($userId, $userType = 'user', $isAdmin = false)
    {
        return $this->participants()->create([
            'participant_id' => $userId,
            'participant_type' => $userType,
            'is_admin' => $isAdmin,
            'joined_at' => now()
        ]);
    }

    /**
     * Remove participant from conversation
     */
    public function removeParticipant($userId, $userType = 'user')
    {
        return $this->participants()
            ->where('participant_id', $userId)
            ->where('participant_type', $userType)
            ->update(['left_at' => now()]);
    }
}
