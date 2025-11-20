<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_event_id',
        'user_id',
        'coach_id',
        'email',
        'role',
        'response_status',
        'response_at',
        'response_note',
        'receive_reminders',
        'reminder_sent',
    ];

    protected $casts = [
        'response_at' => 'datetime',
        'receive_reminders' => 'boolean',
        'reminder_sent' => 'boolean',
    ];

    // Relationships

    public function calendarEvent()
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    // Scopes

    public function scopeOrganizers($query)
    {
        return $query->where('role', 'organizer');
    }

    public function scopeRequired($query)
    {
        return $query->where('role', 'required');
    }

    public function scopeOptional($query)
    {
        return $query->where('role', 'optional');
    }

    public function scopeAccepted($query)
    {
        return $query->where('response_status', 'accepted');
    }

    public function scopeDeclined($query)
    {
        return $query->where('response_status', 'declined');
    }

    public function scopePending($query)
    {
        return $query->where('response_status', 'pending');
    }

    // Methods

    public function accept($note = null)
    {
        $this->response_status = 'accepted';
        $this->response_at = now();
        $this->response_note = $note;
        $this->save();
    }

    public function decline($note = null)
    {
        $this->response_status = 'declined';
        $this->response_at = now();
        $this->response_note = $note;
        $this->save();
    }

    public function tentative($note = null)
    {
        $this->response_status = 'tentative';
        $this->response_at = now();
        $this->response_note = $note;
        $this->save();
    }

    public function isOrganizer()
    {
        return $this->role === 'organizer';
    }

    public function isRequired()
    {
        return $this->role === 'required';
    }

    public function hasResponded()
    {
        return $this->response_status !== 'pending' && $this->response_status !== 'no_response';
    }
}
