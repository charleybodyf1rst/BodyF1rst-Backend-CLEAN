<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AvailabilityController extends Controller
{
    public function getAvailableSlots($id)
    {
        try {
            $date = request()->get('date', now()->toDateString());

            $slots = DB::table('coach_availability')
                ->where('coach_id', $id)
                ->where('date', $date)
                ->where('is_available', true)
                ->where('is_booked', false)
                ->orderBy('start_time')
                ->get();

            return response()->json(['success' => true, 'data' => $slots]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    public function setAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $coachId = auth()->id();

            $availabilityId = DB::table('coach_availability')->insertGetId([
                'coach_id' => $coachId,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'is_available' => true,
                'is_booked' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Availability set successfully', 'data' => ['id' => $availabilityId]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error setting availability'], 500);
        }
    }

    public function getAvailability(Request $request)
    {
        try {
            $coachId = auth()->id();
            $startDate = $request->get('start_date', now()->toDateString());
            $endDate = $request->get('end_date', now()->addDays(30)->toDateString());

            $availability = DB::table('coach_availability')
                ->where('coach_id', $coachId)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();

            return response()->json(['success' => true, 'data' => $availability]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    public function updateAvailability(Request $request, $id)
    {
        try {
            $coachId = auth()->id();

            DB::table('coach_availability')
                ->where('id', $id)
                ->where('coach_id', $coachId)
                ->update([
                    'date' => $request->date,
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                    'is_available' => $request->is_available ?? true,
                    'updated_at' => now()
                ]);

            return response()->json(['success' => true, 'message' => 'Availability updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating availability'], 500);
        }
    }

    public function deleteAvailability($id)
    {
        try {
            $coachId = auth()->id();

            DB::table('coach_availability')
                ->where('id', $id)
                ->where('coach_id', $coachId)
                ->delete();

            return response()->json(['success' => true, 'message' => 'Availability deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting availability'], 500);
        }
    }
}
