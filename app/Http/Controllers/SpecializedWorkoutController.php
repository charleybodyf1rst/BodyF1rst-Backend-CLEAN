<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SpecializedWorkoutController extends Controller
{
    // ==================== AMRAP WORKOUTS ====================

    public function createAMRAP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'amrap_type' => 'required|in:rounds,reps',
            'time_cap_minutes' => 'required|integer|min:1',
            'exercises' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $amrapId = DB::table('amrap_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'amrap_type' => $request->amrap_type,
            'time_cap_minutes' => $request->time_cap_minutes,
            'exercises' => $request->exercises,
            'total_exercises_per_round' => $request->total_exercises_per_round ?? 1,
            'prescribed_reps_per_round' => $request->prescribed_reps_per_round,
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AMRAP workout created successfully',
            'data' => ['id' => $amrapId]
        ]);
    }

    public function logAMRAP(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rounds_completed' => 'required|integer|min:0',
            'total_reps_completed' => 'integer|min:0',
            'partial_round_reps' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::table('amrap_workouts')->where('id', $id)->update([
            'rounds_completed' => $request->rounds_completed,
            'total_reps_completed' => $request->total_reps_completed ?? 0,
            'partial_round_reps' => $request->partial_round_reps ?? 0,
            'score' => $request->rounds_completed + ($request->partial_round_reps / 100),
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'is_rx' => $request->is_rx ?? false,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AMRAP workout logged successfully'
        ]);
    }

    public function getAMRAPHistory($userId)
    {
        $workouts = DB::table('amrap_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== EMOM WORKOUTS ====================

    public function createEMOM(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'total_minutes' => 'required|integer|min:1',
            'exercises' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $emomId = DB::table('emom_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'minute_interval' => $request->minute_interval ?? 1,
            'total_minutes' => $request->total_minutes,
            'exercises_per_minute' => $request->exercises_per_minute ?? 1,
            'exercises' => $request->exercises,
            'alternating' => $request->alternating ?? false,
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'EMOM workout created successfully',
            'data' => ['id' => $emomId]
        ]);
    }

    public function logEMOM(Request $request, $id)
    {
        DB::table('emom_workouts')->where('id', $id)->update([
            'minutes_completed' => $request->minutes_completed,
            'total_reps_completed' => $request->total_reps_completed ?? 0,
            'missed_reps' => $request->missed_reps ?? 0,
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'is_rx' => $request->is_rx ?? false,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'EMOM workout logged successfully'
        ]);
    }

    public function getEMOMHistory($userId)
    {
        $workouts = DB::table('emom_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== RFT WORKOUTS ====================

    public function createRFT(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'prescribed_rounds' => 'required|integer|min:1',
            'exercises' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $rftId = DB::table('rft_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'prescribed_rounds' => $request->prescribed_rounds,
            'exercises' => $request->exercises,
            'exercises_per_round' => $request->exercises_per_round ?? 1,
            'time_cap_seconds' => $request->time_cap_seconds,
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'RFT workout created successfully',
            'data' => ['id' => $rftId]
        ]);
    }

    public function logRFT(Request $request, $id)
    {
        $timeSeconds = $request->time_to_complete_seconds;
        $timeFormatted = sprintf('%d:%02d', floor($timeSeconds / 60), $timeSeconds % 60);

        DB::table('rft_workouts')->where('id', $id)->update([
            'rounds_completed' => $request->rounds_completed,
            'reps_in_partial_round' => $request->reps_in_partial_round ?? 0,
            'time_to_complete_seconds' => $timeSeconds,
            'time_formatted' => $timeFormatted,
            'is_capped' => $request->is_capped ?? false,
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'is_rx' => $request->is_rx ?? false,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'RFT workout logged successfully'
        ]);
    }

    public function getRFTHistory($userId)
    {
        $workouts = DB::table('rft_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== TABATA WORKOUTS ====================

    public function createTabata(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'exercises' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $tabataId = DB::table('tabata_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'work_seconds' => $request->work_seconds ?? 20,
            'rest_seconds' => $request->rest_seconds ?? 10,
            'rounds_per_exercise' => $request->rounds_per_exercise ?? 8,
            'total_tabata_sets' => $request->total_tabata_sets ?? 1,
            'rest_between_sets_seconds' => $request->rest_between_sets_seconds,
            'exercises' => $request->exercises,
            'is_standard_protocol' => ($request->work_seconds == 20 && $request->rest_seconds == 10 && $request->rounds_per_exercise == 8),
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tabata workout created successfully',
            'data' => ['id' => $tabataId]
        ]);
    }

    public function logTabata(Request $request, $id)
    {
        DB::table('tabata_workouts')->where('id', $id)->update([
            'total_rounds_completed' => $request->total_rounds_completed,
            'total_reps_completed' => $request->total_reps_completed,
            'total_duration_seconds' => $request->total_duration_seconds,
            'rounds_data' => $request->rounds_data,
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tabata workout logged successfully'
        ]);
    }

    public function getTabataHistory($userId)
    {
        $workouts = DB::table('tabata_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== HIIT WORKOUTS ====================

    public function createHIIT(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'work_seconds' => 'required|integer|min:1',
            'rest_seconds' => 'required|integer|min:0',
            'total_rounds' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $hiitId = DB::table('hiit_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'work_seconds' => $request->work_seconds,
            'rest_seconds' => $request->rest_seconds,
            'total_rounds' => $request->total_rounds,
            'warmup_seconds' => $request->warmup_seconds,
            'cooldown_seconds' => $request->cooldown_seconds,
            'hiit_type' => $request->hiit_type,
            'work_to_rest_ratio' => $request->work_seconds / max($request->rest_seconds, 1),
            'intervals' => $request->intervals,
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'HIIT workout created successfully',
            'data' => ['id' => $hiitId]
        ]);
    }

    public function logHIIT(Request $request, $id)
    {
        DB::table('hiit_workouts')->where('id', $id)->update([
            'rounds_completed' => $request->rounds_completed,
            'total_work_seconds' => $request->total_work_seconds,
            'total_rest_seconds' => $request->total_rest_seconds,
            'total_duration_seconds' => $request->total_duration_seconds,
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'average_power_watts' => $request->average_power_watts,
            'distance_total' => $request->distance_total,
            'distance_unit' => $request->distance_unit,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'HIIT workout logged successfully'
        ]);
    }

    public function getHIITHistory($userId)
    {
        $workouts = DB::table('hiit_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== CIRCUIT WORKOUTS ====================

    public function createCircuit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'total_stations' => 'required|integer|min:1',
            'stations' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $circuitId = DB::table('circuit_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'total_stations' => $request->total_stations,
            'total_circuits' => $request->total_circuits ?? 1,
            'stations' => $request->stations,
            'work_seconds_per_station' => $request->work_seconds_per_station,
            'rest_seconds_between_stations' => $request->rest_seconds_between_stations,
            'rest_seconds_between_circuits' => $request->rest_seconds_between_circuits,
            'circuit_type' => $request->circuit_type,
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Circuit workout created successfully',
            'data' => ['id' => $circuitId]
        ]);
    }

    public function logCircuit(Request $request, $id)
    {
        DB::table('circuit_workouts')->where('id', $id)->update([
            'circuits_completed' => $request->circuits_completed,
            'total_reps_completed' => $request->total_reps_completed,
            'total_duration_seconds' => $request->total_duration_seconds,
            'circuit_times' => $request->circuit_times,
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Circuit workout logged successfully'
        ]);
    }

    public function getCircuitHistory($userId)
    {
        $workouts = DB::table('circuit_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== SUPERSET WORKOUTS ====================

    public function createSuperset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'total_supersets' => 'required|integer|min:1',
            'supersets' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $supersetId = DB::table('superset_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'superset_type' => $request->superset_type ?? 'standard',
            'total_supersets' => $request->total_supersets,
            'supersets' => $request->supersets,
            'sets_per_superset' => $request->sets_per_superset ?? 3,
            'rest_between_exercises_seconds' => $request->rest_between_exercises_seconds ?? 0,
            'rest_between_sets_seconds' => $request->rest_between_sets_seconds,
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Superset workout created successfully',
            'data' => ['id' => $supersetId]
        ]);
    }

    public function logSuperset(Request $request, $id)
    {
        DB::table('superset_workouts')->where('id', $id)->update([
            'total_sets_completed' => $request->total_sets_completed,
            'total_reps_completed' => $request->total_reps_completed,
            'total_volume' => $request->total_volume,
            'total_duration_seconds' => $request->total_duration_seconds,
            'sets_data' => $request->sets_data,
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Superset workout logged successfully'
        ]);
    }

    public function getSupersetHistory($userId)
    {
        $workouts = DB::table('superset_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== PYRAMID WORKOUTS ====================

    public function createPyramid(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'pyramid_type' => 'required|in:ascending,descending,triangle',
            'progression_method' => 'required|in:weight,reps,both',
            'total_sets' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $pyramidId = DB::table('pyramid_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'pyramid_type' => $request->pyramid_type,
            'progression_method' => $request->progression_method,
            'total_sets' => $request->total_sets,
            'exercises' => $request->exercises,
            'pyramid_structure' => $request->pyramid_structure,
            'starting_reps' => $request->starting_reps,
            'ending_reps' => $request->ending_reps,
            'starting_weight' => $request->starting_weight,
            'ending_weight' => $request->ending_weight,
            'weight_unit' => $request->weight_unit ?? 'lbs',
            'rep_increment' => $request->rep_increment,
            'weight_increment' => $request->weight_increment,
            'rest_seconds_between_sets' => $request->rest_seconds_between_sets,
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pyramid workout created successfully',
            'data' => ['id' => $pyramidId]
        ]);
    }

    public function logPyramid(Request $request, $id)
    {
        DB::table('pyramid_workouts')->where('id', $id)->update([
            'sets_completed' => $request->sets_completed,
            'total_reps_completed' => $request->total_reps_completed,
            'total_volume' => $request->total_volume,
            'sets_data' => $request->sets_data,
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pyramid workout logged successfully'
        ]);
    }

    public function getPyramidHistory($userId)
    {
        $workouts = DB::table('pyramid_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== CHIPPER WORKOUTS ====================

    public function createChipper(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'total_exercises' => 'required|integer|min:1',
            'total_reps_prescribed' => 'required|integer|min:1',
            'exercises' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $chipperId = DB::table('chipper_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'total_exercises' => $request->total_exercises,
            'exercises' => $request->exercises,
            'total_reps_prescribed' => $request->total_reps_prescribed,
            'time_cap_seconds' => $request->time_cap_seconds,
            'partition_strategy' => $request->partition_strategy,
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'CHIPPER workout created successfully',
            'data' => ['id' => $chipperId]
        ]);
    }

    public function logChipper(Request $request, $id)
    {
        $timeSeconds = $request->time_to_complete_seconds;
        $timeFormatted = $timeSeconds ? sprintf('%d:%02d', floor($timeSeconds / 60), $timeSeconds % 60) : null;

        DB::table('chipper_workouts')->where('id', $id)->update([
            'total_reps_completed' => $request->total_reps_completed,
            'exercises_completed' => $request->exercises_completed,
            'current_exercise_reps' => $request->current_exercise_reps,
            'time_to_complete_seconds' => $timeSeconds,
            'time_formatted' => $timeFormatted,
            'is_capped' => $request->is_capped ?? false,
            'progress_checkpoints' => $request->progress_checkpoints,
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'is_rx' => $request->is_rx ?? false,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'CHIPPER workout logged successfully'
        ]);
    }

    public function getChipperHistory($userId)
    {
        $workouts = DB::table('chipper_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== DROP SET WORKOUTS ====================

    public function createDropSet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'workout_name' => 'required|string|max:255',
            'exercise_name' => 'required|string|max:255',
            'total_drop_sets' => 'required|integer|min:1',
            'starting_weight' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dropSetId = DB::table('drop_set_workouts')->insertGetId([
            'user_id' => Auth::id(),
            'workout_log_id' => $request->workout_log_id,
            'workout_name' => $request->workout_name,
            'description' => $request->description,
            'exercise_id' => $request->exercise_id,
            'exercise_name' => $request->exercise_name,
            'total_drop_sets' => $request->total_drop_sets,
            'exercises' => $request->exercises,
            'drops_per_set' => $request->drops_per_set ?? 3,
            'starting_weight' => $request->starting_weight,
            'weight_unit' => $request->weight_unit ?? 'lbs',
            'drop_percentage' => $request->drop_percentage ?? 20.00,
            'rest_between_drops_seconds' => $request->rest_between_drops_seconds ?? 0,
            'rest_between_sets_seconds' => $request->rest_between_sets_seconds,
            'to_failure' => $request->to_failure ?? true,
            'workout_date' => $request->workout_date ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Drop Set workout created successfully',
            'data' => ['id' => $dropSetId]
        ]);
    }

    public function logDropSet(Request $request, $id)
    {
        DB::table('drop_set_workouts')->where('id', $id)->update([
            'total_reps_completed' => $request->total_reps_completed,
            'total_volume' => $request->total_volume,
            'total_duration_seconds' => $request->total_duration_seconds,
            'sets_data' => $request->sets_data,
            'calories_burned' => $request->calories_burned,
            'average_heart_rate' => $request->average_heart_rate,
            'max_heart_rate' => $request->max_heart_rate,
            'perceived_exertion' => $request->perceived_exertion,
            'notes' => $request->notes,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Drop Set workout logged successfully'
        ]);
    }

    public function getDropSetHistory($userId)
    {
        $workouts = DB::table('drop_set_workouts')
            ->where('user_id', $userId)
            ->orderBy('workout_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workouts
        ]);
    }

    // ==================== UNIFIED GET METHODS ====================

    public function getAllWorkoutTypes($userId)
    {
        $data = [
            'amrap' => DB::table('amrap_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
            'emom' => DB::table('emom_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
            'rft' => DB::table('rft_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
            'tabata' => DB::table('tabata_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
            'hiit' => DB::table('hiit_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
            'circuit' => DB::table('circuit_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
            'superset' => DB::table('superset_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
            'pyramid' => DB::table('pyramid_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
            'chipper' => DB::table('chipper_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
            'drop_set' => DB::table('drop_set_workouts')->where('user_id', $userId)->orderBy('workout_date', 'desc')->limit(10)->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function getWorkoutTypeStats($userId, $type)
    {
        $tableName = $type . '_workouts';

        $stats = [
            'total_workouts' => DB::table($tableName)->where('user_id', $userId)->count(),
            'workouts_this_month' => DB::table($tableName)
                ->where('user_id', $userId)
                ->whereMonth('workout_date', now()->month)
                ->whereYear('workout_date', now()->year)
                ->count(),
            'average_heart_rate' => DB::table($tableName)
                ->where('user_id', $userId)
                ->whereNotNull('average_heart_rate')
                ->avg('average_heart_rate'),
            'total_calories' => DB::table($tableName)
                ->where('user_id', $userId)
                ->sum('calories_burned'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
