<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\WorkoutPlan;
use App\Models\WorkoutPlanAssignment;

class WorkoutPlanController extends Controller
{
    /**
     * Create a new workout plan
     * POST /api/workout-plans
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_weeks' => 'nullable|integer|min:1|max:52',
            'sessions_per_week' => 'nullable|integer|min:1|max:7',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
            'goal' => 'nullable|string|in:strength,hypertrophy,endurance,general_fitness',
            'exercises' => 'nullable|array',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $workoutPlan = WorkoutPlan::create([
                'coach_id' => $request->user()->id,
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'duration_weeks' => $request->input('duration_weeks', 4),
                'sessions_per_week' => $request->input('sessions_per_week', 3),
                'difficulty_level' => $request->input('difficulty_level'),
                'goal' => $request->input('goal'),
                'exercises' => $request->input('exercises'),
                'notes' => $request->input('notes')
            ]);

            Log::info('Workout plan created', [
                'plan_id' => $workoutPlan->id,
                'coach_id' => $request->user()->id,
                'name' => $workoutPlan->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Workout plan created successfully',
                'data' => $workoutPlan
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating workout plan', [
                'error' => $e->getMessage(),
                'coach_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create workout plan'
            ], 500);
        }
    }

    /**
     * Get a single workout plan by ID
     * GET /api/workout-plans/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $workoutPlan = WorkoutPlan::with(['coach', 'assignments'])->find($id);

            if (!$workoutPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workout plan not found'
                ], 404);
            }

            // Authorization: Only the coach who created the plan can view it
            if ($workoutPlan->coach_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this workout plan'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $workoutPlan
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching workout plan', [
                'error' => $e->getMessage(),
                'plan_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workout plan'
            ], 500);
        }
    }

    /**
     * List all workout plans for the authenticated coach
     * GET /api/coaches/workout-plans
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = WorkoutPlan::where('coach_id', $request->user()->id)
                ->with('assignments');

            // Optional filters
            if ($request->has('difficulty_level')) {
                $query->where('difficulty_level', $request->input('difficulty_level'));
            }

            if ($request->has('goal')) {
                $query->where('goal', $request->input('goal'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->input('per_page', 15);
            $workoutPlans = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $workoutPlans
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching workout plans', [
                'error' => $e->getMessage(),
                'coach_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workout plans'
            ], 500);
        }
    }

    /**
     * Update an existing workout plan
     * PUT /api/workout-plans/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'duration_weeks' => 'nullable|integer|min:1|max:52',
            'sessions_per_week' => 'nullable|integer|min:1|max:7',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
            'goal' => 'nullable|string|in:strength,hypertrophy,endurance,general_fitness',
            'exercises' => 'nullable|array',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $workoutPlan = WorkoutPlan::find($id);

            if (!$workoutPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workout plan not found'
                ], 404);
            }

            // Authorization: Only the coach who created the plan can update it
            if ($workoutPlan->coach_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this workout plan'
                ], 403);
            }

            $workoutPlan->update($request->only([
                'name', 'description', 'duration_weeks', 'sessions_per_week',
                'difficulty_level', 'goal', 'exercises', 'notes'
            ]));

            Log::info('Workout plan updated', [
                'plan_id' => $workoutPlan->id,
                'coach_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Workout plan updated successfully',
                'data' => $workoutPlan->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating workout plan', [
                'error' => $e->getMessage(),
                'plan_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update workout plan'
            ], 500);
        }
    }

    /**
     * Delete a workout plan
     * DELETE /api/workout-plans/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $workoutPlan = WorkoutPlan::find($id);

            if (!$workoutPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workout plan not found'
                ], 404);
            }

            // Authorization: Only the coach who created the plan can delete it
            if ($workoutPlan->coach_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this workout plan'
                ], 403);
            }

            $planName = $workoutPlan->name;
            $workoutPlan->delete();

            Log::info('Workout plan deleted', [
                'plan_id' => $id,
                'plan_name' => $planName,
                'coach_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Workout plan deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting workout plan', [
                'error' => $e->getMessage(),
                'plan_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete workout plan'
            ], 500);
        }
    }

    /**
     * Assign workout plan to one or more clients
     * POST /api/coaches/assign-workout-plan
     */
    public function assign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workout_plan_id' => 'required|integer|exists:workout_plans,id',
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'required|integer|exists:users,id',
            'assigned_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $workoutPlan = WorkoutPlan::find($request->input('workout_plan_id'));

            // Authorization: Only the coach who created the plan can assign it
            if ($workoutPlan->coach_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to assign this workout plan'
                ], 403);
            }

            $assignedDate = $request->input('assigned_date', now()->toDateString());
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $notes = $request->input('notes');
            $clientIds = $request->input('client_ids');

            $assignments = [];

            DB::beginTransaction();

            foreach ($clientIds as $clientId) {
                $assignment = WorkoutPlanAssignment::create([
                    'workout_plan_id' => $workoutPlan->id,
                    'client_id' => $clientId,
                    'assigned_by' => $request->user()->id,
                    'assigned_date' => $assignedDate,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'active',
                    'notes' => $notes
                ]);

                $assignments[] = $assignment;
            }

            DB::commit();

            Log::info('Workout plan assigned to clients', [
                'plan_id' => $workoutPlan->id,
                'coach_id' => $request->user()->id,
                'client_count' => count($clientIds)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Workout plan assigned successfully',
                'data' => [
                    'workout_plan' => $workoutPlan,
                    'assignments' => $assignments
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error assigning workout plan', [
                'error' => $e->getMessage(),
                'plan_id' => $request->input('workout_plan_id')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign workout plan'
            ], 500);
        }
    }
}
