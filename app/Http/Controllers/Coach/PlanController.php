<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    public function assignWorkout(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'workout_plan_id' => 'required|integer',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $coachId = auth()->id();

            $assignmentId = DB::table('workout_plan_assignments')->insertGetId([
                'coach_id' => $coachId,
                'user_id' => $id,
                'workout_plan_id' => $request->workout_plan_id,
                'start_date' => $request->start_date ?? now(),
                'status' => 'active',
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Workout plan assigned successfully', 'data' => ['id' => $assignmentId]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error assigning workout plan'], 500);
        }
    }

    public function assignNutritionPlan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nutrition_plan_id' => 'required|integer',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $coachId = auth()->id();

            $assignmentId = DB::table('nutrition_plan_assignments')->insertGetId([
                'coach_id' => $coachId,
                'user_id' => $id,
                'nutrition_plan_id' => $request->nutrition_plan_id,
                'start_date' => $request->start_date ?? now(),
                'status' => 'active',
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Nutrition plan assigned successfully', 'data' => ['id' => $assignmentId]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error assigning nutrition plan'], 500);
        }
    }
}
