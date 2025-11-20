<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    public function getAppointments(Request $request)
    {
        try {
            $coachId = auth()->id();
            $status = $request->get('status');

            $query = DB::table('coach_appointments')->where('coach_id', $coachId);

            if ($status) {
                $query->where('status', $status);
            }

            $appointments = $query->orderBy('appointment_date', 'desc')->get();

            return response()->json(['success' => true, 'data' => $appointments]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    public function createAppointment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|integer',
            'appointment_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'type' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $coachId = auth()->id();

            $appointmentId = DB::table('coach_appointments')->insertGetId([
                'coach_id' => $coachId,
                'client_id' => $request->client_id,
                'appointment_date' => $request->appointment_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'type' => $request->type ?? 'consultation',
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Appointment created successfully', 'data' => ['id' => $appointmentId]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error creating appointment'], 500);
        }
    }

    public function getAppointment($id)
    {
        try {
            $coachId = auth()->id();

            $appointment = DB::table('coach_appointments')
                ->where('id', $id)
                ->where('coach_id', $coachId)
                ->first();

            if (!$appointment) {
                return response()->json(['success' => false, 'message' => 'Appointment not found'], 404);
            }

            return response()->json(['success' => true, 'data' => $appointment]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching appointment'], 500);
        }
    }

    public function updateAppointment(Request $request, $id)
    {
        try {
            $coachId = auth()->id();

            DB::table('coach_appointments')
                ->where('id', $id)
                ->where('coach_id', $coachId)
                ->update([
                    'appointment_date' => $request->appointment_date,
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                    'status' => $request->status ?? 'scheduled',
                    'notes' => $request->notes,
                    'updated_at' => now()
                ]);

            return response()->json(['success' => true, 'message' => 'Appointment updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating appointment'], 500);
        }
    }

    public function cancelAppointment($id)
    {
        try {
            $coachId = auth()->id();

            DB::table('coach_appointments')
                ->where('id', $id)
                ->where('coach_id', $coachId)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'updated_at' => now()
                ]);

            return response()->json(['success' => true, 'message' => 'Appointment cancelled successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error cancelling appointment'], 500);
        }
    }

    public function completeAppointment(Request $request, $id)
    {
        try {
            $coachId = auth()->id();

            DB::table('coach_appointments')
                ->where('id', $id)
                ->where('coach_id', $coachId)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'session_notes' => $request->session_notes,
                    'updated_at' => now()
                ]);

            return response()->json(['success' => true, 'message' => 'Appointment marked as completed']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error completing appointment'], 500);
        }
    }
}
