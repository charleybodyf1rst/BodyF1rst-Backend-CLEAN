<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarBlockedTime extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'coach_id',
        'start_time',
        'end_time',
        'reason',
        'notes',
        'block_type',
        'recurring_pattern_id',
        'color',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // Relationships

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function recurringPattern()
    {
        return $this->belongsTo(CalendarRecurringPattern::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('end_time', '>=', now());
    }

    public function scopeForCoach($query, $coachId)
    {
        return $query->where('coach_id', $coachId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_time', [$startDate, $endDate])
              ->orWhereBetween('end_time', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_time', '<=', $startDate)
                     ->where('end_time', '>=', $endDate);
              });
        });
    }

    public function scopeConflictsWith($query, $startTime, $endTime)
    {
        return $query->where(function ($q) use ($startTime, $endTime) {
            $q->whereBetween('start_time', [$startTime, $endTime])
              ->orWhereBetween('end_time', [$startTime, $endTime])
              ->orWhere(function ($q2) use ($startTime, $endTime) {
                  $q2->where('start_time', '<=', $startTime)
                     ->where('end_time', '>=', $endTime);
              });
        });
    }

    // Methods

    public function isActive()
    {
        return $this->end_time >= now();
    }

    public function conflictsWith($startTime, $endTime)
    {
        return $this->start_time < $endTime && $this->end_time > $startTime;
    }
}
