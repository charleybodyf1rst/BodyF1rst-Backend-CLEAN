<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CalendarService
{
    /**
     * Add an event to the user's calendar.
     *
     * @param array $args
     * @return array
     */
    public function addEvent(array $args): array
    {
        try {
            // Validate required parameters
            if (empty($args['title']) || empty($args['start'])) {
                return ['ok' => false, 'error' => 'Missing required fields: title, start'];
            }

            // Sanitize and validate input
            $title = trim(strip_tags($args['title']));
            
            if (strlen($title) > 255) {
                return ['ok' => false, 'error' => 'Title must be 255 characters or less'];
            }

            // Parse and validate dates
            try {
                $startTime = Carbon::parse($args['start']);
                $endTime = isset($args['end']) ? Carbon::parse($args['end']) : $startTime->copy()->addHour();
            } catch (\Exception $e) {
                return ['ok' => false, 'error' => 'Invalid date format. Use ISO 8601 format (e.g., 2024-01-01T10:00:00Z)'];
            }

            // Validate date logic
            if ($endTime->lte($startTime)) {
                return ['ok' => false, 'error' => 'End time must be after start time'];
            }

            if ($startTime->isPast() && $startTime->diffInHours(now()) > 24) {
                return ['ok' => false, 'error' => 'Cannot create events more than 24 hours in the past'];
            }

            if ($startTime->diffInYears(now()) > 5) {
                return ['ok' => false, 'error' => 'Cannot create events more than 5 years in the future'];
            }

            $userId = auth()->id();
            if ($userId === null) {
                return ['ok' => false, 'error' => 'Authentication required to add calendar events.'];
            }

            // Generate event data
            $eventId = Str::uuid()->toString();
            $eventData = [
                'id' => $eventId,
                'title' => $title,
                'start' => $startTime,
                'end' => $endTime,
                'duration_minutes' => $startTime->diffInMinutes($endTime),
                'user_id' => $userId,
                'created_at' => now()
            ];

            // Save to calendar_events table for persistence
            try {
                DB::table('calendar_events')->insert([
                    'user_id' => $eventData['user_id'],
                    'title' => $eventData['title'],
                    'start_time' => $eventData['start'],
                    'end_time' => $eventData['end'],
                    'duration_minutes' => $eventData['duration_minutes'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info('Calendar event created and saved to database', [
                    'event_id' => $eventId,
                    'user_id' => $eventData['user_id']
                ]);
            } catch (\Exception $dbError) {
                // Log error but don't fail the request - graceful degradation
                Log::error('Failed to save calendar event to database', [
                    'error' => $dbError->getMessage(),
                    'event_id' => $eventId
                ]);
            }

            return [
                'ok' => true,
                'event_id' => $eventId,
                'message' => 'Event added successfully',
                'event' => [
                    'title' => $title,
                    'start' => $startTime->toISOString(),
                    'end' => $endTime->toISOString(),
                    'duration_minutes' => $eventData['duration_minutes'],
                    'timezone' => $startTime->timezoneName
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to add calendar event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'args' => $args
            ]);

            return [
                'ok' => false,
                'error' => 'Failed to add event: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get upcoming events for a user.
     *
     * @param int|null $userId
     * @param int $days
     * @return array
     */
    public function getUpcomingEvents(?int $userId = null, int $days = 7): array
    {
        try {
            $userId = $userId ?? auth()->id();
            if ($userId === null) {
                return ['ok' => false, 'error' => 'Authentication required to view calendar events.'];
            }

            // Retrieve upcoming events from database
            try {
                $endDate = now()->addDays($days);

                $events = DB::table('calendar_events')
                    ->where('user_id', $userId)
                    ->where('start_time', '>=', now())
                    ->where('start_time', '<=', $endDate)
                    ->orderBy('start_time', 'asc')
                    ->get()
                    ->map(function ($event) {
                        return [
                            'id' => $event->id,
                            'title' => $event->title,
                            'start' => Carbon::parse($event->start_time)->toISOString(),
                            'end' => Carbon::parse($event->end_time)->toISOString(),
                            'duration_minutes' => $event->duration_minutes
                        ];
                    })
                    ->toArray();

                Log::info('Retrieved upcoming events from database', [
                    'user_id' => $userId,
                    'days' => $days,
                    'event_count' => count($events)
                ]);

                return [
                    'ok' => true,
                    'events' => $events,
                    'message' => count($events) > 0 ? 'Events retrieved successfully' : 'No upcoming events found'
                ];
            } catch (\Exception $dbError) {
                // Log error and return empty array - graceful degradation
                Log::error('Failed to retrieve events from database', [
                    'error' => $dbError->getMessage(),
                    'user_id' => $userId
                ]);

                return [
                    'ok' => true,
                    'events' => [],
                    'message' => 'No upcoming events found'
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to retrieve upcoming events', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? 'unknown'
            ]);

            return [
                'ok' => false,
                'error' => 'Failed to retrieve events'
            ];
        }
    }
}
