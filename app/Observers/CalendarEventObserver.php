<?php

namespace App\Observers;

use App\Models\CalendarEvent;
use App\Models\BodyPoint;
use App\Models\ActivityLog;
use App\Services\ComprehensiveCalendarService;
use Illuminate\Support\Facades\Log;

class CalendarEventObserver
{
    /**
     * Handle the CalendarEvent "created" event.
     */
    public function created(CalendarEvent $event)
    {
        // Log activity
        $this->logActivity($event, 'created');

        // Award body points for scheduling
        $this->awardSchedulingPoints($event);
    }

    /**
     * Handle the CalendarEvent "updated" event.
     */
    public function updated(CalendarEvent $event)
    {
        // Log activity
        $this->logActivity($event, 'updated');

        // Check if event was just completed
        if ($event->isDirty('status') && $event->status === 'completed') {
            $this->handleEventCompletion($event);
        }

        // Check if event was cancelled
        if ($event->isDirty('status') && $event->status === 'cancelled') {
            $this->handleEventCancellation($event);
        }
    }

    /**
     * Handle the CalendarEvent "deleted" event.
     */
    public function deleted(CalendarEvent $event)
    {
        // Log activity
        $this->logActivity($event, 'deleted');
    }

    /**
     * Handle the CalendarEvent "restored" event.
     */
    public function restored(CalendarEvent $event)
    {
        // Log activity
        $this->logActivity($event, 'restored');
    }

    /**
     * Log activity for audit trail
     */
    protected function logActivity(CalendarEvent $event, $action)
    {
        try {
            $userId = $event->user_id ?? auth()->id();
            $coachId = $event->coach_id ?? auth()->guard('coach')->id();

            ActivityLog::create([
                'user_id' => $userId,
                'coach_id' => $coachId,
                'action' => "calendar_event_{$action}",
                'description' => "Calendar event '{$event->title}' was {$action}",
                'model_type' => CalendarEvent::class,
                'model_id' => $event->id,
                'properties' => json_encode([
                    'event_type' => $event->event_type,
                    'title' => $event->title,
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                    'status' => $event->status,
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log calendar event activity: ' . $e->getMessage());
        }
    }

    /**
     * Award body points for scheduling events
     */
    protected function awardSchedulingPoints(CalendarEvent $event)
    {
        if (!$event->user_id) {
            return;
        }

        // Only award points for certain event types
        $pointsMap = [
            'workout' => 5,
            'meal' => 3,
            'checkin' => 10,
            'personal' => 2,
        ];

        if (!isset($pointsMap[$event->event_type])) {
            return;
        }

        try {
            $points = $pointsMap[$event->event_type];

            BodyPoint::create([
                'user_id' => $event->user_id,
                'points' => $points,
                'activity_type' => "calendar_schedule_{$event->event_type}",
                'description' => "Scheduled {$event->event_type}: {$event->title}",
                'metadata' => json_encode([
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                ]),
            ]);

            // Update user's total body points
            $event->user->increment('body_points', $points);

            Log::info("Awarded {$points} body points for scheduling event", [
                'user_id' => $event->user_id,
                'event_id' => $event->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to award scheduling points: ' . $e->getMessage());
        }
    }

    /**
     * Handle event completion
     */
    protected function handleEventCompletion(CalendarEvent $event)
    {
        if (!$event->user_id) {
            return;
        }

        try {
            // Award completion points (higher than scheduling)
            $completionPointsMap = [
                'workout' => 25,
                'meal' => 10,
                'checkin' => 50,
                'cbt_session' => 40,
                'assessment' => 30,
                'personal' => 5,
            ];

            $points = $completionPointsMap[$event->event_type] ?? 10;

            BodyPoint::create([
                'user_id' => $event->user_id,
                'points' => $points,
                'activity_type' => "calendar_complete_{$event->event_type}",
                'description' => "Completed {$event->event_type}: {$event->title}",
                'metadata' => json_encode([
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'completed_at' => $event->completed_at,
                ]),
            ]);

            // Update event's body points awarded
            $event->body_points_awarded = $points;
            $event->saveQuietly(); // Don't trigger observer again

            // Update user's total body points
            $event->user->increment('body_points', $points);

            // Update streaks
            $this->updateStreaks($event);

            // Check for bonus achievements
            $this->checkCompletionBonuses($event);

            Log::info("Awarded {$points} body points for completing event", [
                'user_id' => $event->user_id,
                'event_id' => $event->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle event completion: ' . $e->getMessage());
        }
    }

    /**
     * Handle event cancellation
     */
    protected function handleEventCancellation(CalendarEvent $event)
    {
        try {
            // Could deduct points or send notification
            Log::info('Event cancelled', [
                'event_id' => $event->id,
                'title' => $event->title,
                'reason' => $event->cancellation_reason,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle event cancellation: ' . $e->getMessage());
        }
    }

    /**
     * Update user streaks
     */
    protected function updateStreaks(CalendarEvent $event)
    {
        try {
            $streakTypeMap = [
                'workout' => 'workout',
                'meal' => 'nutrition',
                'checkin' => 'checkin',
            ];

            if (!isset($streakTypeMap[$event->event_type])) {
                return;
            }

            $streakType = $streakTypeMap[$event->event_type];
            $calendarService = app(ComprehensiveCalendarService::class);

            $calendarService->updateStreaks(
                $event->user_id,
                $streakType,
                $event->start_time->toDateString()
            );

            Log::info('Updated streak for completed event', [
                'user_id' => $event->user_id,
                'streak_type' => $streakType,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update streaks: ' . $e->getMessage());
        }
    }

    /**
     * Check for completion bonuses
     */
    protected function checkCompletionBonuses(CalendarEvent $event)
    {
        try {
            $today = $event->start_time->toDateString();

            // Check if user completed all events for the day
            $todayEvents = CalendarEvent::forUser($event->user_id)
                ->whereDate('start_time', $today)
                ->where('status', '!=', 'cancelled')
                ->get();

            $completedCount = $todayEvents->where('status', 'completed')->count();
            $totalCount = $todayEvents->count();

            // Perfect day bonus (100% completion)
            if ($completedCount === $totalCount && $totalCount > 0) {
                $bonusPoints = 50;

                BodyPoint::create([
                    'user_id' => $event->user_id,
                    'points' => $bonusPoints,
                    'activity_type' => 'perfect_day_bonus',
                    'description' => "Perfect day! Completed all {$totalCount} scheduled activities",
                    'metadata' => json_encode([
                        'date' => $today,
                        'completed_count' => $completedCount,
                    ]),
                ]);

                $event->user->increment('body_points', $bonusPoints);

                Log::info('Awarded perfect day bonus', [
                    'user_id' => $event->user_id,
                    'date' => $today,
                    'bonus' => $bonusPoints,
                ]);
            }

            // Check weekly completion
            $this->checkWeeklyBonus($event);
        } catch (\Exception $e) {
            Log::error('Failed to check completion bonuses: ' . $e->getMessage());
        }
    }

    /**
     * Check for weekly completion bonus
     */
    protected function checkWeeklyBonus(CalendarEvent $event)
    {
        try {
            $weekStart = $event->start_time->copy()->startOfWeek();
            $weekEnd = $event->start_time->copy()->endOfWeek();

            // Get all events for this week
            $weekEvents = CalendarEvent::forUser($event->user_id)
                ->whereBetween('start_time', [$weekStart, $weekEnd])
                ->where('status', '!=', 'cancelled')
                ->get();

            $completedCount = $weekEvents->where('status', 'completed')->count();
            $totalCount = $weekEvents->count();

            // Award bonus for high weekly completion (80%+)
            if ($totalCount > 0) {
                $completionRate = ($completedCount / $totalCount) * 100;

                if ($completionRate >= 80 && $completionRate < 100) {
                    // Check if bonus already awarded this week
                    $existingBonus = BodyPoint::where('user_id', $event->user_id)
                        ->where('activity_type', 'weekly_completion_bonus')
                        ->where('created_at', '>=', $weekStart)
                        ->exists();

                    if (!$existingBonus) {
                        $bonusPoints = 100;

                        BodyPoint::create([
                            'user_id' => $event->user_id,
                            'points' => $bonusPoints,
                            'activity_type' => 'weekly_completion_bonus',
                            'description' => "Great week! {$completionRate}% completion rate",
                            'metadata' => json_encode([
                                'week_start' => $weekStart->toDateString(),
                                'completion_rate' => $completionRate,
                                'completed' => $completedCount,
                                'total' => $totalCount,
                            ]),
                        ]);

                        $event->user->increment('body_points', $bonusPoints);

                        Log::info('Awarded weekly completion bonus', [
                            'user_id' => $event->user_id,
                            'completion_rate' => $completionRate,
                            'bonus' => $bonusPoints,
                        ]);
                    }
                } elseif ($completionRate === 100) {
                    // Perfect week bonus
                    $existingBonus = BodyPoint::where('user_id', $event->user_id)
                        ->where('activity_type', 'perfect_week_bonus')
                        ->where('created_at', '>=', $weekStart)
                        ->exists();

                    if (!$existingBonus) {
                        $bonusPoints = 200;

                        BodyPoint::create([
                            'user_id' => $event->user_id,
                            'points' => $bonusPoints,
                            'activity_type' => 'perfect_week_bonus',
                            'description' => "Perfect week! 100% completion on all {$totalCount} activities",
                            'metadata' => json_encode([
                                'week_start' => $weekStart->toDateString(),
                                'completed' => $completedCount,
                            ]),
                        ]);

                        $event->user->increment('body_points', $bonusPoints);

                        Log::info('Awarded perfect week bonus', [
                            'user_id' => $event->user_id,
                            'bonus' => $bonusPoints,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to check weekly bonus: ' . $e->getMessage());
        }
    }
}
