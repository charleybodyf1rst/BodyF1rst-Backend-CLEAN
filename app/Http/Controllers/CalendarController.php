<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\CalendarRecurringPattern;
use App\Models\CalendarBlockedTime;
use App\Services\ComprehensiveCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CalendarController extends Controller
{
    protected $calendarService;

    public function __construct(ComprehensiveCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    // ========================================
    // COACH ENDPOINTS
    // ========================================

    /**
     * GET /api/calendar/coach/overview
     * Full calendar view with all events
     */
    public function coachOverview(Request $request)
    {
        try {
            $coachId = auth()->guard('coach')->id();

            $data = $this->calendarService->getCoachOverview(
                $coachId,
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching coach calendar overview: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch calendar overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/coach/month/{year}/{month}
     * Month view with event counts and colors
     */
    public function coachMonthView($year, $month)
    {
        try {
            $coachId = auth()->guard('coach')->id();

            $data = $this->calendarService->getMonthView($coachId, $year, $month, true);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching coach month view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch month view',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/coach/week
     * Week view with time slots
     */
    public function coachWeekView(Request $request)
    {
        try {
            $coachId = auth()->guard('coach')->id();

            $data = $this->calendarService->getWeekView(
                $coachId,
                $request->start_date,
                true
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching coach week view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch week view',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/coach/day/{date}
     * Day view with detailed schedule
     */
    public function coachDayView($date)
    {
        try {
            $coachId = auth()->guard('coach')->id();

            $data = $this->calendarService->getDayView($coachId, $date, true);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching coach day view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch day view',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/coach/events
     * Get events with filters
     */
    public function coachEvents(Request $request)
    {
        try {
            $coachId = auth()->guard('coach')->id();

            $query = CalendarEvent::forCoach($coachId);

            // Apply filters
            if ($request->has('type')) {
                $query->byType($request->type);
            }

            if ($request->has('client_id')) {
                $query->where('user_id', $request->client_id);
            }

            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            if ($request->has('upcoming') && $request->upcoming == 'true') {
                $query->upcoming();
            }

            $events = $query->with(['user', 'appointment', 'workout', 'checkin'])
                ->orderBy('start_time', $request->sort ?? 'asc')
                ->paginate($request->per_page ?? 50);

            return response()->json([
                'success' => true,
                'data' => $events
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching coach events: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/calendar/coach/block-time
     * Block time slots
     */
    public function blockTime(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'block_type' => 'required|in:unavailable,break,personal,vacation,holiday,other',
            'recurring' => 'nullable|boolean',
            'recurring_pattern' => 'required_if:recurring,true|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $coachId = auth()->guard('coach')->id();

            $blockedTime = CalendarBlockedTime::create([
                'coach_id' => $coachId,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'block_type' => $request->block_type,
                'color' => $request->color ?? '#6B7280',
            ]);

            // Handle recurring pattern if specified
            if ($request->recurring && $request->recurring_pattern) {
                $pattern = CalendarRecurringPattern::create($request->recurring_pattern);
                $blockedTime->update(['recurring_pattern_id' => $pattern->id]);

                // Generate recurring instances
                $occurrences = $pattern->getNextOccurrences(90);
                foreach ($occurrences as $date) {
                    CalendarBlockedTime::create([
                        'coach_id' => $coachId,
                        'start_time' => $date->copy()
                            ->hour(Carbon::parse($request->start_time)->hour)
                            ->minute(Carbon::parse($request->start_time)->minute),
                        'end_time' => $date->copy()
                            ->hour(Carbon::parse($request->end_time)->hour)
                            ->minute(Carbon::parse($request->end_time)->minute),
                        'reason' => $request->reason,
                        'notes' => $request->notes,
                        'block_type' => $request->block_type,
                        'recurring_pattern_id' => $pattern->id,
                        'color' => $request->color ?? '#6B7280',
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Time blocked successfully',
                'data' => $blockedTime
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error blocking time: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to block time',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/coach/availability
     * Get available time slots for booking
     */
    public function coachAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'duration' => 'nullable|integer|min:15|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $coachId = auth()->guard('coach')->id();

            $availability = $this->calendarService->getAvailableSlots(
                $coachId,
                $request->date,
                $request->duration ?? 60
            );

            return response()->json([
                'success' => true,
                'data' => $availability
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching availability: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/calendar/coach/reschedule/{appointmentId}
     * Reschedule appointment
     */
    public function rescheduleAppointment(Request $request, $appointmentId)
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date|after:now',
            'end_time' => 'nullable|date|after:start_time',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $event = CalendarEvent::where('appointment_id', $appointmentId)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Check for conflicts
            if ($event->hasConflicts()) {
                $conflicts = $event->getConflicts();
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule conflict detected',
                    'conflicts' => $conflicts
                ], 409);
            }

            $event->reschedule(
                Carbon::parse($request->start_time),
                $request->end_time ? Carbon::parse($request->end_time) : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled successfully',
                'data' => $event
            ]);
        } catch (\Exception $e) {
            Log::error('Error rescheduling appointment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reschedule appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========================================
    // CUSTOMER/MOBILE ENDPOINTS
    // ========================================

    /**
     * GET /api/calendar/my-calendar
     * Personal calendar view
     */
    public function myCalendar(Request $request)
    {
        try {
            $userId = auth()->id();

            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

            $events = CalendarEvent::forUser($userId)
                ->dateRange($startDate, $endDate)
                ->with(['coach', 'workout', 'checkin'])
                ->orderBy('start_time', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'events' => $events,
                    'summary' => $this->getUserCalendarSummary($userId, $startDate, $endDate),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user calendar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch calendar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/month/{year}/{month}
     * Month view with streaks and progress
     */
    public function monthView($year, $month)
    {
        try {
            $userId = auth()->id();

            $data = $this->calendarService->getMonthView($userId, $year, $month, false);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching month view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch month view',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/week
     * Week view with daily goals
     */
    public function weekView(Request $request)
    {
        try {
            $userId = auth()->id();

            $data = $this->calendarService->getWeekView(
                $userId,
                $request->start_date,
                false
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching week view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch week view',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/day/{date}
     * Day view with detailed tasks
     */
    public function dayView($date)
    {
        try {
            $userId = auth()->id();

            $data = $this->calendarService->getDayView($userId, $date, false);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching day view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch day view',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/calendar/add-event
     * Add personal event/reminder
     */
    public function addEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'all_day' => 'nullable|boolean',
            'reminder_times' => 'nullable|array',
            'color' => 'nullable|string|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();

            $startTime = Carbon::parse($request->start_time);
            $endTime = $request->end_time
                ? Carbon::parse($request->end_time)
                : $startTime->copy()->addHour();

            $event = CalendarEvent::create([
                'user_id' => $userId,
                'event_type' => 'personal',
                'title' => $request->title,
                'description' => $request->description,
                'location' => $request->location,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $startTime->diffInMinutes($endTime),
                'all_day' => $request->all_day ?? false,
                'color' => $request->color ?? '#EAB308',
                'reminder_enabled' => true,
                'reminder_times' => $request->reminder_times ?? [1440, 60], // 1 day, 1 hour
                'status' => 'scheduled',
            ]);

            $event->createReminders();

            return response()->json([
                'success' => true,
                'message' => 'Event added successfully',
                'data' => $event
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error adding event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/upcoming
     * Next 7 days overview
     */
    public function upcoming(Request $request)
    {
        try {
            $userId = auth()->id();
            $days = $request->days ?? 7;

            $events = CalendarEvent::forUser($userId)
                ->where('start_time', '>=', now())
                ->where('start_time', '<=', now()->addDays($days))
                ->orderBy('start_time', 'asc')
                ->with(['workout', 'checkin', 'appointment'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'events' => $events,
                    'total' => $events->count(),
                    'by_type' => [
                        'workouts' => $events->where('event_type', 'workout')->count(),
                        'meals' => $events->where('event_type', 'meal')->count(),
                        'appointments' => $events->where('event_type', 'appointment')->count(),
                        'checkins' => $events->where('event_type', 'checkin')->count(),
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching upcoming events: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upcoming events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/calendar/streaks
     * Track workout/nutrition streaks
     */
    public function streaks()
    {
        try {
            $userId = auth()->id();

            $streaks = $this->calendarService->getStreaks($userId);

            return response()->json([
                'success' => true,
                'data' => [
                    'streaks' => $streaks,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching streaks: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch streaks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/calendar/sync
     * Sync with external calendars
     */
    public function syncExternalCalendar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:google,apple,outlook,office365',
            'access_token' => 'required|string',
            'refresh_token' => 'nullable|string',
            'calendar_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // External calendar sync would be implemented here
            // This is a placeholder for the sync logic

            return response()->json([
                'success' => true,
                'message' => 'Calendar sync initiated',
                'data' => [
                    'provider' => $request->provider,
                    'status' => 'syncing',
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error syncing calendar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync calendar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    protected function getUserCalendarSummary($userId, $startDate, $endDate)
    {
        $events = CalendarEvent::forUser($userId)
            ->dateRange($startDate, $endDate)
            ->get();

        return [
            'total_events' => $events->count(),
            'workouts_planned' => $events->where('event_type', 'workout')->count(),
            'workouts_completed' => $events->where('event_type', 'workout')
                ->where('status', 'completed')->count(),
            'meals_logged' => $events->where('event_type', 'meal')->count(),
            'appointments' => $events->where('event_type', 'appointment')->count(),
            'body_points_earned' => $events->sum('body_points_awarded'),
            'completion_rate' => $events->count() > 0
                ? round(($events->where('status', 'completed')->count() / $events->count()) * 100)
                : 0,
        ];
    }
}
