<?php

namespace App\Http\Controllers\Coach;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $coach = auth()->user();

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

            // Get workout plan details
            $workoutPlan = DB::table('workout_plans')->where('id', $request->workout_plan_id)->first();
            $client = DB::table('users')->where('id', $id)->first();

            // Send push notification to client
            if ($workoutPlan && $client) {
                $title = 'New Workout Plan Assigned';
                $message = "{$coach->first_name} has assigned you a new workout plan: {$workoutPlan->name}";

                Helper::sendPush($title, $message, null, null, 'workout_assigned', $assignmentId, [$id]);
                Log::info("Workout plan assignment notification sent to user {$id}");
            }

            return response()->json(['success' => true, 'message' => 'Workout plan assigned successfully', 'data' => ['id' => $assignmentId]]);
        } catch (\Exception $e) {
            Log::error("Error assigning workout plan: " . $e->getMessage());
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
            $coach = auth()->user();

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

            // Get nutrition plan details
            $nutritionPlan = DB::table('nutrition_plans')->where('id', $request->nutrition_plan_id)->first();
            $client = DB::table('users')->where('id', $id)->first();

            // Send push notification to client
            if ($nutritionPlan && $client) {
                $title = 'New Nutrition Plan Assigned';
                $message = "{$coach->first_name} has assigned you a new nutrition plan: {$nutritionPlan->name}";

                Helper::sendPush($title, $message, null, null, 'nutrition_assigned', $assignmentId, [$id]);
                Log::info("Nutrition plan assignment notification sent to user {$id}");
            }

            return response()->json(['success' => true, 'message' => 'Nutrition plan assigned successfully', 'data' => ['id' => $assignmentId]]);
        } catch (\Exception $e) {
            Log::error("Error assigning nutrition plan: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error assigning nutrition plan'], 500);
        }
    }
}
