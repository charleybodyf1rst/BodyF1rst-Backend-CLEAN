<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'client_id',
        'title',
        'type',
        'scheduled_at',
        'end_time',
        'duration',
        'location',
        'notes',
        'status',
        'cancellation_reason',
        'reminder_sent',
        'reminder_sent_at'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'end_time' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
    ];

    /**
     * Get the coach for this appointment
     */
    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    /**
     * Get the client for this appointment
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Scope to get upcoming appointments
     */
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>', now())
                     ->where('status', '!=', 'cancelled');
    }

    /**
     * Scope to get past appointments
     */
    public function scopePast($query)
    {
        return $query->where('scheduled_at', '<', now());
    }

    /**
     * Scope to get confirmed appointments
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope to get appointments needing reminders
     */
    public function scopeNeedingReminders($query)
    {
        return $query->where('scheduled_at', '>', now())
                     ->where('scheduled_at', '<=', now()->addDay())
                     ->where('status', 'scheduled')
                     ->where(function($q) {
                         $q->where('reminder_sent', false)
                           ->orWhereNull('reminder_sent');
                     });
    }

    /**
     * Check if appointment is upcoming
     */
    public function isUpcoming()
    {
        return $this->scheduled_at > now() && $this->status !== 'cancelled';
    }

    /**
     * Check if appointment needs reminder
     */
    public function needsReminder()
    {
        return $this->scheduled_at > now() &&
               $this->scheduled_at <= now()->addDay() &&
               $this->status === 'scheduled' &&
               !$this->reminder_sent;
    }

    /**
     * Mark reminder as sent
     */
    public function markReminderSent()
    {
        $this->reminder_sent = true;
        $this->reminder_sent_at = now();
        $this->save();
    }
}
