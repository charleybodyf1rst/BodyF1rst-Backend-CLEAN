<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CalendarEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'coach_id',
        'organization_id',
        'event_type',
        'title',
        'description',
        'location',
        'meeting_url',
        'start_time',
        'end_time',
        'duration',
        'all_day',
        'timezone',
        'color',
        'icon',
        'status',
        'appointment_id',
        'workout_id',
        'checkin_id',
        'meal_plan_id',
        'assessment_id',
        'recurring_pattern_id',
        'parent_event_id',
        'recurrence_date',
        'metadata',
        'notes',
        'cancellation_reason',
        'reminder_enabled',
        'reminder_times',
        'last_reminder_sent_at',
        'completed_at',
        'completed_by',
        'body_points_awarded',
        'external_calendar_id',
        'external_event_id',
        'ical_uid',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'all_day' => 'boolean',
        'reminder_enabled' => 'boolean',
        'reminder_times' => 'array',
        'metadata' => 'array',
        'last_reminder_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'body_points_awarded' => 'integer',
    ];

    protected $appends = [
        'color_code',
        'is_past',
        'is_today',
        'is_upcoming',
        'is_recurring',
    ];

    // Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function checkin()
    {
        return $this->belongsTo(WeeklyCheckin::class);
    }

    public function workout()
    {
        return $this->belongsTo(Workout::class);
    }

    public function mealPlan()
    {
        return $this->belongsTo(MealPlanTemplate::class, 'meal_plan_id');
    }

    public function assessment()
    {
        return $this->belongsTo(AssessmentData::class);
    }

    public function recurringPattern()
    {
        return $this->belongsTo(CalendarRecurringPattern::class);
    }

    public function parentEvent()
    {
        return $this->belongsTo(CalendarEvent::class, 'parent_event_id');
    }

    public function childEvents()
    {
        return $this->hasMany(CalendarEvent::class, 'parent_event_id');
    }

    public function participants()
    {
        return $this->hasMany(CalendarEventParticipant::class);
    }

    public function reminders()
    {
        return $this->hasMany(CalendarEventReminder::class);
    }

    // Scopes

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>=', now())
                     ->where('status', '!=', 'cancelled')
                     ->orderBy('start_time', 'asc');
    }

    public function scopePast($query)
    {
        return $query->where('start_time', '<', now())
                     ->orderBy('start_time', 'desc');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('start_time', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('start_time', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ]);
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

    public function scopeByType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCoach($query, $coachId)
    {
        return $query->where(function ($q) use ($coachId) {
            $q->where('coach_id', $coachId)
              ->orWhereHas('user', function ($q2) use ($coachId) {
                  $q2->where('coach_id', $coachId);
              });
        });
    }

    public function scopeNeedsReminder($query)
    {
        return $query->where('reminder_enabled', true)
                     ->where('status', 'scheduled')
                     ->where('start_time', '>', now());
    }

    public function scopeConflictsWith($query, $startTime, $endTime, $excludeId = null)
    {
        $query->where(function ($q) use ($startTime, $endTime) {
            $q->whereBetween('start_time', [$startTime, $endTime])
              ->orWhereBetween('end_time', [$startTime, $endTime])
              ->orWhere(function ($q2) use ($startTime, $endTime) {
                  $q2->where('start_time', '<=', $startTime)
                     ->where('end_time', '>=', $endTime);
              });
        })->where('status', '!=', 'cancelled');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }

    // Accessors

    public function getColorCodeAttribute()
    {
        if ($this->color) {
            return $this->color;
        }

        // Default colors by event type
        $colors = [
            'appointment' => '#3B82F6',    // Blue
            'workout' => '#10B981',        // Green
            'meal' => '#F59E0B',           // Orange
            'checkin' => '#8B5CF6',        // Purple
            'cbt_session' => '#14B8A6',    // Teal
            'assessment' => '#EF4444',     // Red
            'blocked_time' => '#6B7280',   // Gray
            'personal' => '#EAB308',       // Yellow
            'reminder' => '#06B6D4',       // Cyan
        ];

        return $colors[$this->event_type] ?? '#3B82F6';
    }

    public function getIsPastAttribute()
    {
        return $this->end_time < now();
    }

    public function getIsTodayAttribute()
    {
        return $this->start_time->isToday();
    }

    public function getIsUpcomingAttribute()
    {
        return $this->start_time > now();
    }

    public function getIsRecurringAttribute()
    {
        return $this->recurring_pattern_id !== null;
    }

    // Methods

    public function markAsCompleted($completedBy = null)
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->completed_by = $completedBy;
        $this->save();

        return $this;
    }

    public function cancel($reason = null)
    {
        $this->status = 'cancelled';
        $this->cancellation_reason = $reason;
        $this->save();

        return $this;
    }

    public function reschedule($newStartTime, $newEndTime = null)
    {
        $this->start_time = $newStartTime;

        if ($newEndTime) {
            $this->end_time = $newEndTime;
            $this->duration = $this->start_time->diffInMinutes($this->end_time);
        } else {
            $this->end_time = $newStartTime->copy()->addMinutes($this->duration);
        }

        $this->status = 'scheduled';
        $this->last_reminder_sent_at = null;
        $this->save();

        // Recreate reminders
        $this->createReminders();

        return $this;
    }

    public function hasConflicts($excludeSelf = true)
    {
        $query = static::where(function ($q) {
            if ($this->coach_id) {
                $q->where('coach_id', $this->coach_id);
            } elseif ($this->user_id) {
                $q->where('user_id', $this->user_id);
            }
        })->conflictsWith($this->start_time, $this->end_time);

        if ($excludeSelf && $this->id) {
            $query->where('id', '!=', $this->id);
        }

        return $query->exists();
    }

    public function getConflicts($excludeSelf = true)
    {
        $query = static::where(function ($q) {
            if ($this->coach_id) {
                $q->where('coach_id', $this->coach_id);
            } elseif ($this->user_id) {
                $q->where('user_id', $this->user_id);
            }
        })->conflictsWith($this->start_time, $this->end_time);

        if ($excludeSelf && $this->id) {
            $query->where('id', '!=', $this->id);
        }

        return $query->get();
    }

    public function createReminders()
    {
        if (!$this->reminder_enabled || !$this->reminder_times) {
            return;
        }

        // Delete existing reminders
        $this->reminders()->delete();

        foreach ($this->reminder_times as $minutesBefore) {
            $scheduledFor = $this->start_time->copy()->subMinutes($minutesBefore);

            // Only create reminder if it's in the future
            if ($scheduledFor > now()) {
                CalendarEventReminder::create([
                    'calendar_event_id' => $this->id,
                    'minutes_before' => $minutesBefore,
                    'scheduled_for' => $scheduledFor,
                    'method' => 'push', // Default to push
                ]);
            }
        }
    }

    public function toICalFormat()
    {
        $ical = "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . ($this->ical_uid ?: uniqid()) . "\r\n";
        $ical .= "DTSTAMP:" . now()->format('Ymd\THis\Z') . "\r\n";
        $ical .= "DTSTART:" . $this->start_time->format('Ymd\THis\Z') . "\r\n";
        $ical .= "DTEND:" . $this->end_time->format('Ymd\THis\Z') . "\r\n";
        $ical .= "SUMMARY:" . $this->title . "\r\n";

        if ($this->description) {
            $ical .= "DESCRIPTION:" . str_replace("\n", "\\n", $this->description) . "\r\n";
        }

        if ($this->location) {
            $ical .= "LOCATION:" . $this->location . "\r\n";
        }

        $ical .= "STATUS:" . strtoupper($this->status) . "\r\n";
        $ical .= "END:VEVENT\r\n";

        return $ical;
    }
}
