<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    protected $notificationService;

    public function __construct(AppointmentNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of appointments
     */
    public function index(Request $request)
    {
        $query = Appointment::with(['coach', 'client']);

        // Filter by coach
        if ($request->has('coach_id')) {
            $query->where('coach_id', $request->coach_id);
        }

        // Filter by client
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('scheduled_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('scheduled_at', '<=', $request->end_date);
        }

        // Filter upcoming or past
        if ($request->has('upcoming') && $request->upcoming == 'true') {
            $query->upcoming();
        }

        if ($request->has('past') && $request->past == 'true') {
            $query->past();
        }

        $appointments = $query->orderBy('scheduled_at', 'asc')->get();

        return response()->json($appointments);
    }

    /**
     * Store a newly created appointment
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coach_id' => 'required|exists:coaches,id',
            'client_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:session,check-in,consultation,assessment,other',
            'scheduled_at' => 'required|date|after:now',
            'duration' => 'required|integer|min:15|max:300',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $appointment = Appointment::create([
                'coach_id' => $request->coach_id,
                'client_id' => $request->client_id,
                'title' => $request->title,
                'type' => $request->type,
                'scheduled_at' => $request->scheduled_at,
                'end_time' => date('Y-m-d H:i:s', strtotime($request->scheduled_at . ' +' . $request->duration . ' minutes')),
                'duration' => $request->duration,
                'location' => $request->location,
                'notes' => $request->notes,
                'status' => 'scheduled'
            ]);

            // Load relationships for email
            $appointment->load(['coach', 'client']);

            // Send confirmation email
            $this->notificationService->sendConfirmation($appointment);

            return response()->json([
                'message' => 'Appointment created successfully',
                'appointment' => $appointment
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating appointment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified appointment
     */
    public function show($id)
    {
        $appointment = Appointment::with(['coach', 'client'])->find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }

        return response()->json($appointment);
    }

    /**
     * Update the specified appointment
     */
    public function update(Request $request, $id)
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:session,check-in,consultation,assessment,other',
            'scheduled_at' => 'sometimes|date',
            'duration' => 'sometimes|integer|min:15|max:300',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:scheduled,completed,cancelled,no-show,rescheduled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if this is a reschedule
            $isReschedule = $request->has('scheduled_at') &&
                          $request->scheduled_at != $appointment->scheduled_at;

            $oldAppointment = clone $appointment;

            $appointment->update($request->all());

            if ($request->has('scheduled_at') && $request->has('duration')) {
                $appointment->end_time = date('Y-m-d H:i:s',
                    strtotime($request->scheduled_at . ' +' . $request->duration . ' minutes'));
                $appointment->save();
            }

            // Load relationships for email
            $appointment->load(['coach', 'client']);

            // Send rescheduled email if date/time changed
            if ($isReschedule) {
                $this->notificationService->sendRescheduled($oldAppointment, $appointment);
                // Reset reminder flag
                $appointment->reminder_sent = false;
                $appointment->reminder_sent_at = null;
                $appointment->save();
            }

            return response()->json([
                'message' => 'Appointment updated successfully',
                'appointment' => $appointment
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating appointment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an appointment
     */
    public function cancel(Request $request, $id)
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }

        try {
            $appointment->status = 'cancelled';
            $appointment->cancellation_reason = $request->reason;
            $appointment->save();

            // Load relationships for email
            $appointment->load(['coach', 'client']);

            // Send cancellation email
            $this->notificationService->sendCancellation($appointment, $request->reason);

            return response()->json([
                'message' => 'Appointment cancelled successfully',
                'appointment' => $appointment
            ]);
        } catch (\Exception $e) {
            Log::error('Error cancelling appointment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to cancel appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete the specified appointment
     */
    public function destroy($id)
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }

        try {
            // Load relationships for email before deleting
            $appointment->load(['coach', 'client']);

            // Send cancellation email
            $this->notificationService->sendCancellation($appointment, 'Appointment deleted by staff');

            $appointment->delete();

            return response()->json([
                'message' => 'Appointment deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting appointment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send reminders for upcoming appointments (to be called by scheduled task)
     */
    public function sendReminders()
    {
        try {
            $count = $this->notificationService->sendUpcomingReminders();

            return response()->json([
                'message' => "Sent {$count} appointment reminders",
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending reminders: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark appointment as no-show
     */
    public function markNoShow($id)
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }

        try {
            $appointment->status = 'no-show';
            $appointment->save();

            // Load relationships for email
            $appointment->load(['coach', 'client']);

            // Send no-show follow-up email
            $this->notificationService->sendNoShowFollowUp($appointment);

            return response()->json([
                'message' => 'Appointment marked as no-show',
                'appointment' => $appointment
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking no-show: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to mark as no-show',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
