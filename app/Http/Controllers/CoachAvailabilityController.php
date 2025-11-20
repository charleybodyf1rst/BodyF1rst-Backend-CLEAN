<?php

namespace App\Http\Controllers;

use App\Models\CoachAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CoachAvailabilityController extends Controller
{
    /**
     * Get all availability blocks for a coach
     */
    public function index(Request $request)
    {
        $query = CoachAvailability::query();

        // Filter by coach
        if ($request->has('coach_id')) {
            $query->where('coach_id', $request->coach_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where(function($q) use ($request) {
                $q->where('end_date', '>=', $request->start_date)
                  ->orWhereNull('end_date');
            });
        }

        if ($request->has('end_date')) {
            $query->where('start_date', '<=', $request->end_date);
        }

        // Filter by day of week
        if ($request->has('day_of_week')) {
            $query->where('day_of_week', $request->day_of_week);
        }

        $availability = $query->orderBy('start_date', 'asc')
                             ->orderBy('start_time', 'asc')
                             ->get();

        return response()->json($availability);
    }

    /**
     * Store a new availability block
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coach_id' => 'required|exists:coaches,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'day_of_week' => 'nullable|integer|between:0,6',
            'is_recurring' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $availability = CoachAvailability::create($request->all());

            return response()->json([
                'message' => 'Availability created successfully',
                'availability' => $availability
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating availability: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified availability
     */
    public function show($id)
    {
        $availability = CoachAvailability::find($id);

        if (!$availability) {
            return response()->json(['message' => 'Availability not found'], 404);
        }

        return response()->json($availability);
    }

    /**
     * Update the specified availability
     */
    public function update(Request $request, $id)
    {
        $availability = CoachAvailability::find($id);

        if (!$availability) {
            return response()->json(['message' => 'Availability not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'day_of_week' => 'nullable|integer|between:0,6',
            'is_recurring' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $availability->update($request->all());

            return response()->json([
                'message' => 'Availability updated successfully',
                'availability' => $availability
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating availability: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete the specified availability
     */
    public function destroy($id)
    {
        $availability = CoachAvailability::find($id);

        if (!$availability) {
            return response()->json(['message' => 'Availability not found'], 404);
        }

        try {
            $availability->delete();

            return response()->json([
                'message' => 'Availability deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting availability: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available time slots for a coach on a specific date
     */
    public function getAvailableSlots(Request $request, $coachId)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'duration' => 'integer|min:15|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $date = Carbon::parse($request->date);
            $dayOfWeek = $date->dayOfWeek;
            $duration = $request->duration ?? 60;

            // Get availability blocks for this coach and date
            $availabilityBlocks = CoachAvailability::where('coach_id', $coachId)
                ->where(function($query) use ($date, $dayOfWeek) {
                    $query->where(function($q) use ($date) {
                        // One-time availability
                        $q->where('is_recurring', false)
                          ->where('start_date', '<=', $date->format('Y-m-d'))
                          ->where(function($subQ) use ($date) {
                              $subQ->where('end_date', '>=', $date->format('Y-m-d'))
                                   ->orWhereNull('end_date');
                          });
                    })->orWhere(function($q) use ($dayOfWeek) {
                        // Recurring availability
                        $q->where('is_recurring', true)
                          ->where('day_of_week', $dayOfWeek);
                    });
                })
                ->get();

            // Get existing appointments for this date
            $existingAppointments = \App\Models\Appointment::where('coach_id', $coachId)
                ->whereDate('scheduled_at', $date->format('Y-m-d'))
                ->where('status', '!=', 'cancelled')
                ->get();

            // Generate available time slots
            $slots = [];
            foreach ($availabilityBlocks as $block) {
                $slots = array_merge($slots, $this->generateSlots(
                    $date,
                    $block->start_time,
                    $block->end_time,
                    $duration,
                    $existingAppointments
                ));
            }

            // Sort slots by time
            usort($slots, function($a, $b) {
                return strcmp($a['time'], $b['time']);
            });

            return response()->json([
                'date' => $date->format('Y-m-d'),
                'slots' => $slots
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting available slots: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get available slots',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate time slots for a given availability block
     */
    private function generateSlots($date, $startTime, $endTime, $duration, $existingAppointments)
    {
        $slots = [];
        $current = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);
        $end = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime);

        while ($current->addMinutes($duration) <= $end) {
            $slotStart = $current->copy()->subMinutes($duration);
            $slotEnd = $current->copy();

            // Check if this slot overlaps with any existing appointment
            $isAvailable = true;
            foreach ($existingAppointments as $appointment) {
                $appointmentStart = Carbon::parse($appointment->scheduled_at);
                $appointmentEnd = Carbon::parse($appointment->end_time);

                if ($slotStart < $appointmentEnd && $slotEnd > $appointmentStart) {
                    $isAvailable = false;
                    break;
                }
            }

            $slots[] = [
                'time' => $slotStart->format('g:i A'),
                'datetime' => $slotStart->toISOString(),
                'available' => $isAvailable
            ];
        }

        return $slots;
    }
}
