<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarRecurringPattern;
use App\Models\CalendarBlockedTime;
use App\Models\CalendarStreak;
use App\Models\Appointment;
use App\Models\WeeklyCheckin;
use App\Models\UserCompletedWorkout;
use App\Models\UserNutrition;
use App\Models\BodyPoint;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComprehensiveCalendarService
{
    /**
     * Get coach's calendar overview with all events
     */
    public function getCoachOverview($coachId, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();

        $events = CalendarEvent::forCoach($coachId)
            ->dateRange($startDate, $endDate)
            ->with(['user', 'appointment', 'workout', 'checkin'])
            ->orderBy('start_time', 'asc')
            ->get();

        $blockedTimes = CalendarBlockedTime::forCoach($coachId)
            ->dateRange($startDate, $endDate)
            ->get();

        return [
            'events' => $events->map(fn($e) => $this->formatEventForCalendar($e)),
            'blocked_times' => $blockedTimes->map(fn($b) => $this->formatBlockedTimeForCalendar($b)),
            'summary' => $this->getCoachSummary($coachId, $startDate, $endDate),
        ];
    }

    /**
     * Get month view with event counts and colors
     */
    public function getMonthView($userId, $year, $month, $isCoach = false)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = CalendarEvent::dateRange($startDate, $endDate);

        if ($isCoach) {
            $query->forCoach($userId);
        } else {
            $query->forUser($userId);
        }

        $events = $query->get();

        // Group events by date
        $calendar = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayEvents = $events->filter(function ($event) use ($date) {
                return $event->start_time->isSameDay($date);
            });

            $calendar[$dateString] = [
                'date' => $dateString,
                'events' => $dayEvents->map(fn($e) => $this->formatEventForCalendar($e)),
                'event_count' => $dayEvents->count(),
                'has_workout' => $dayEvents->contains('event_type', 'workout'),
                'has_meal' => $dayEvents->contains('event_type', 'meal'),
                'has_appointment' => $dayEvents->contains('event_type', 'appointment'),
                'has_checkin' => $dayEvents->contains('event_type', 'checkin'),
                'completion_status' => $this->getDayCompletionStatus($dayEvents),
            ];
        }

        return [
            'month' => $month,
            'year' => $year,
            'calendar' => array_values($calendar),
            'summary' => $this->getMonthSummary($events),
        ];
    }

    /**
     * Get week view with time slots
     */
    public function getWeekView($userId, $startDate = null, $isCoach = false)
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfWeek() : now()->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();

        $query = CalendarEvent::dateRange($startDate, $endDate);

        if ($isCoach) {
            $query->forCoach($userId);
        } else {
            $query->forUser($userId);
        }

        $events = $query->with(['user', 'coach'])->get();

        // Create week structure
        $week = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dayEvents = $events->filter(function ($event) use ($date) {
                return $event->start_time->isSameDay($date);
            })->sortBy('start_time')->values();

            $week[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'is_today' => $date->isToday(),
                'events' => $dayEvents->map(fn($e) => $this->formatEventForCalendar($e)),
                'time_slots' => $this->generateTimeSlots($dayEvents, $isCoach ? $userId : null),
            ];
        }

        return [
            'week_start' => $startDate->format('Y-m-d'),
            'week_end' => $endDate->format('Y-m-d'),
            'days' => $week,
            'summary' => $this->getWeekSummary($events, $isCoach ? null : $userId),
        ];
    }

    /**
     * Get day view with detailed schedule
     */
    public function getDayView($userId, $date, $isCoach = false)
    {
        $date = Carbon::parse($date);
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $query = CalendarEvent::dateRange($startOfDay, $endOfDay);

        if ($isCoach) {
            $query->forCoach($userId);
        } else {
            $query->forUser($userId);
        }

        $events = $query->with(['user', 'coach', 'appointment', 'workout', 'checkin'])
            ->orderBy('start_time', 'asc')
            ->get();

        return [
            'date' => $date->format('Y-m-d'),
            'day_name' => $date->format('l'),
            'is_today' => $date->isToday(),
            'events' => $events->map(fn($e) => $this->formatEventForCalendar($e, true)),
            'summary' => $this->getDaySummary($events, $userId, $isCoach),
            'time_slots' => $this->generateTimeSlots($events, $isCoach ? $userId : null),
        ];
    }

    /**
     * Get available time slots for booking
     */
    public function getAvailableSlots($coachId, $date, $duration = 60)
    {
        $date = Carbon::parse($date);
        $startOfDay = $date->copy()->hour(8)->minute(0); // Start at 8 AM
        $endOfDay = $date->copy()->hour(20)->minute(0);  // End at 8 PM

        // Get all events and blocked times for the day
        $events = CalendarEvent::forCoach($coachId)
            ->dateRange($startOfDay, $endOfDay)
            ->where('status', '!=', 'cancelled')
            ->get();

        $blockedTimes = CalendarBlockedTime::forCoach($coachId)
            ->dateRange($startOfDay, $endOfDay)
            ->get();

        // Generate potential slots (every 30 minutes)
        $slots = [];
        $current = $startOfDay->copy();

        while ($current < $endOfDay) {
            $slotEnd = $current->copy()->addMinutes($duration);

            // Check if slot conflicts with any event or blocked time
            $hasConflict = false;

            foreach ($events as $event) {
                if ($current < $event->end_time && $slotEnd > $event->start_time) {
                    $hasConflict = true;
                    break;
                }
            }

            if (!$hasConflict) {
                foreach ($blockedTimes as $blocked) {
                    if ($current < $blocked->end_time && $slotEnd > $blocked->start_time) {
                        $hasConflict = true;
                        break;
                    }
                }
            }

            if (!$hasConflict && $slotEnd <= $endOfDay) {
                $slots[] = [
                    'start_time' => $current->format('Y-m-d H:i:s'),
                    'end_time' => $slotEnd->format('Y-m-d H:i:s'),
                    'available' => true,
                ];
            }

            $current->addMinutes(30); // Move to next potential slot
        }

        return [
            'date' => $date->format('Y-m-d'),
            'duration' => $duration,
            'slots' => $slots,
            'total_available' => count($slots),
        ];
    }

    /**
     * Create or update recurring events
     */
    public function handleRecurringEvent($eventData, $recurringData)
    {
        DB::beginTransaction();

        try {
            // Create recurring pattern
            $pattern = CalendarRecurringPattern::create($recurringData);

            // Create base event
            $eventData['recurring_pattern_id'] = $pattern->id;
            $baseEvent = CalendarEvent::create($eventData);

            // Generate occurrences for the next 90 days
            $occurrences = $pattern->getNextOccurrences(
                count: $recurringData['occurrence_count'] ?? 90,
                fromDate: $recurringData['start_date']
            );

            $createdEvents = [];

            foreach ($occurrences as $occurrenceDate) {
                $instanceData = $eventData;
                $instanceData['parent_event_id'] = $baseEvent->id;
                $instanceData['recurrence_date'] = $occurrenceDate;
                $instanceData['start_time'] = $occurrenceDate->copy()
                    ->hour($baseEvent->start_time->hour)
                    ->minute($baseEvent->start_time->minute);
                $instanceData['end_time'] = $instanceData['start_time']->copy()
                    ->addMinutes($baseEvent->duration);

                $instance = CalendarEvent::create($instanceData);
                $instance->createReminders();

                $createdEvents[] = $instance;
                $pattern->incrementOccurrencesCreated();
            }

            DB::commit();

            return [
                'pattern' => $pattern,
                'base_event' => $baseEvent,
                'instances' => $createdEvents,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating recurring event: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync event from external sources (appointments, workouts, etc.)
     */
    public function syncExternalEvent($eventType, $externalId, $data)
    {
        // Find existing calendar event
        $event = CalendarEvent::where('event_type', $eventType)
            ->where("{$eventType}_id", $externalId)
            ->first();

        if ($event) {
            // Update existing
            $event->update($data);
        } else {
            // Create new
            $data['event_type'] = $eventType;
            $data["{$eventType}_id"] = $externalId;
            $event = CalendarEvent::create($data);
            $event->createReminders();
        }

        return $event;
    }

    /**
     * Update streak data
     */
    public function updateStreaks($userId, $activityType, $date = null)
    {
        $streak = CalendarStreak::firstOrCreate(
            [
                'user_id' => $userId,
                'streak_type' => $activityType,
            ],
            [
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_activities' => 0,
            ]
        );

        $streak->recordActivity($date);

        return $streak;
    }

    /**
     * Get streak information for user
     */
    public function getStreaks($userId)
    {
        $streaks = CalendarStreak::forUser($userId)->get();

        return $streaks->map(function ($streak) {
            return [
                'type' => $streak->streak_type,
                'current_streak' => $streak->current_streak,
                'longest_streak' => $streak->longest_streak,
                'last_activity' => $streak->last_activity_date?->format('Y-m-d'),
                'streak_health' => $streak->getStreakHealth(),
                'total_activities' => $streak->total_activities,
                'milestones' => $streak->milestones ?? [],
                'heat_map' => $streak->getHeatMapData(90), // Last 90 days
            ];
        });
    }

    /**
     * Format event for calendar display
     */
    protected function formatEventForCalendar($event, $detailed = false)
    {
        $formatted = [
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->event_type,
            'start' => $event->start_time->toIso8601String(),
            'end' => $event->end_time->toIso8601String(),
            'color' => $event->color_code,
            'status' => $event->status,
            'all_day' => $event->all_day,
            'is_past' => $event->is_past,
            'is_today' => $event->is_today,
            'is_recurring' => $event->is_recurring,
        ];

        if ($detailed) {
            $formatted['description'] = $event->description;
            $formatted['location'] = $event->location;
            $formatted['meeting_url'] = $event->meeting_url;
            $formatted['notes'] = $event->notes;
            $formatted['duration'] = $event->duration;
            $formatted['user'] = $event->user ? [
                'id' => $event->user->id,
                'name' => $event->user->full_name ?? $event->user->name,
                'email' => $event->user->email,
            ] : null;
            $formatted['coach'] = $event->coach ? [
                'id' => $event->coach->id,
                'name' => $event->coach->full_name ?? $event->coach->name,
            ] : null;
            $formatted['participants'] = $event->participants->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->user?->full_name ?? $p->coach?->full_name ?? $p->email,
                    'role' => $p->role,
                    'status' => $p->response_status,
                ];
            });
        }

        return $formatted;
    }

    /**
     * Format blocked time for calendar
     */
    protected function formatBlockedTimeForCalendar($blocked)
    {
        return [
            'id' => $blocked->id,
            'title' => $blocked->reason ?? 'Blocked',
            'type' => 'blocked_time',
            'block_type' => $blocked->block_type,
            'start' => $blocked->start_time->toIso8601String(),
            'end' => $blocked->end_time->toIso8601String(),
            'color' => $blocked->color,
            'notes' => $blocked->notes,
        ];
    }

    /**
     * Generate time slots for a day
     */
    protected function generateTimeSlots($events, $coachId = null)
    {
        $slots = [];
        $hours = range(6, 22); // 6 AM to 10 PM

        foreach ($hours as $hour) {
            $slotStart = Carbon::today()->hour($hour)->minute(0);
            $slotEnd = $slotStart->copy()->addHour();

            $slotEvents = $events->filter(function ($event) use ($slotStart, $slotEnd) {
                return $event->start_time < $slotEnd && $event->end_time > $slotStart;
            })->values();

            $slots[] = [
                'hour' => $hour,
                'time' => $slotStart->format('g:i A'),
                'events' => $slotEvents->map(fn($e) => $this->formatEventForCalendar($e)),
                'is_busy' => $slotEvents->isNotEmpty(),
            ];
        }

        return $slots;
    }

    /**
     * Get coach summary statistics
     */
    protected function getCoachSummary($coachId, $startDate, $endDate)
    {
        $events = CalendarEvent::forCoach($coachId)
            ->dateRange($startDate, $endDate)
            ->get();

        return [
            'total_events' => $events->count(),
            'appointments' => $events->where('event_type', 'appointment')->count(),
            'checkins_pending' => $events->where('event_type', 'checkin')
                ->where('status', 'scheduled')->count(),
            'completed_events' => $events->where('status', 'completed')->count(),
            'cancelled_events' => $events->where('status', 'cancelled')->count(),
            'upcoming_today' => $events->filter(fn($e) => $e->is_today && $e->is_upcoming)->count(),
        ];
    }

    /**
     * Get month summary
     */
    protected function getMonthSummary($events)
    {
        return [
            'total_events' => $events->count(),
            'workouts' => $events->where('event_type', 'workout')->count(),
            'meals_logged' => $events->where('event_type', 'meal')->count(),
            'appointments' => $events->where('event_type', 'appointment')->count(),
            'checkins' => $events->where('event_type', 'checkin')->count(),
            'completed' => $events->where('status', 'completed')->count(),
        ];
    }

    /**
     * Get week summary
     */
    protected function getWeekSummary($events, $userId = null)
    {
        $summary = [
            'total_events' => $events->count(),
            'completed' => $events->where('status', 'completed')->count(),
            'upcoming' => $events->filter(fn($e) => $e->is_upcoming)->count(),
            'workouts_planned' => $events->where('event_type', 'workout')->count(),
            'workouts_completed' => $events->where('event_type', 'workout')
                ->where('status', 'completed')->count(),
        ];

        if ($userId) {
            $summary['completion_rate'] = $events->count() > 0
                ? round(($summary['completed'] / $events->count()) * 100)
                : 0;
        }

        return $summary;
    }

    /**
     * Get day summary
     */
    protected function getDaySummary($events, $userId, $isCoach)
    {
        $summary = [
            'total_events' => $events->count(),
            'completed' => $events->where('status', 'completed')->count(),
            'pending' => $events->where('status', 'scheduled')->count(),
        ];

        if (!$isCoach) {
            // Add user-specific daily stats
            $summary['calories_goal'] = 2000; // Should come from user profile
            $summary['calories_consumed'] = 0; // Calculate from nutrition logs
            $summary['workouts_completed'] = $events->where('event_type', 'workout')
                ->where('status', 'completed')->count();
            $summary['water_intake'] = 0; // From tracking
            $summary['body_points_earned'] = $events->sum('body_points_awarded');
        }

        return $summary;
    }

    /**
     * Get day completion status
     */
    protected function getDayCompletionStatus($events)
    {
        if ($events->isEmpty()) {
            return 'empty';
        }

        $total = $events->count();
        $completed = $events->where('status', 'completed')->count();

        if ($completed === $total) {
            return 'complete';
        } elseif ($completed > 0) {
            return 'partial';
        } else {
            return 'pending';
        }
    }
}
