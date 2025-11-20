<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEventReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_event_id',
        'minutes_before',
        'scheduled_for',
        'method',
        'status',
        'sent_at',
        'error_message',
        'retry_count',
        'max_retries',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'minutes_before' => 'integer',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    // Relationships

    public function calendarEvent()
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                     ->where('scheduled_for', '<=', now())
                     ->where('retry_count', '<', function ($q) {
                         $q->selectRaw('max_retries');
                     });
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'pending')
                     ->where('scheduled_for', '<=', now());
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('method', $method);
    }

    // Methods

    public function markAsSent()
    {
        $this->status = 'sent';
        $this->sent_at = now();
        $this->save();
    }

    public function markAsFailed($errorMessage = null)
    {
        $this->retry_count++;

        if ($this->retry_count >= $this->max_retries) {
            $this->status = 'failed';
        }

        $this->error_message = $errorMessage;
        $this->save();
    }

    public function cancel()
    {
        $this->status = 'cancelled';
        $this->save();
    }

    public function canRetry()
    {
        return $this->retry_count < $this->max_retries;
    }
}
