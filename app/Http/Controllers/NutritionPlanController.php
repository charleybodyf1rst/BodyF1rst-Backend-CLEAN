<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\NutritionPlan;
use App\Models\NutritionPlanAssignment;

class NutritionPlanController extends Controller
{
    /**
     * Create a new nutrition plan
     * POST /api/nutrition-plans
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'duration_days' => 'nullable|integer|min:1|max:365',
            'daily_calories' => 'required|integer|min:500|max:10000',
            'daily_protein_g' => 'required|numeric|min:0|max:1000',
            'daily_carbs_g' => 'required|numeric|min:0|max:2000',
            'daily_fat_g' => 'required|numeric|min:0|max:500',
            'bmr' => 'nullable|integer|min:500|max:5000',
            'tdee' => 'nullable|integer|min:500|max:10000',
            'goal_type' => 'nullable|string|in:weight_loss,maintenance,muscle_gain',
            'activity_level' => 'nullable|string|in:sedentary,lightly_active,moderately_active,very_active,extremely_active',
            'meals' => 'nullable|array',
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
            $nutritionPlan = NutritionPlan::create([
                'coach_id' => $request->user()->id,
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'start_date' => $request->input('start_date'),
                'duration_days' => $request->input('duration_days', 30),
                'daily_calories' => $request->input('daily_calories'),
                'daily_protein_g' => $request->input('daily_protein_g'),
                'daily_carbs_g' => $request->input('daily_carbs_g'),
                'daily_fat_g' => $request->input('daily_fat_g'),
                'bmr' => $request->input('bmr'),
                'tdee' => $request->input('tdee'),
                'goal_type' => $request->input('goal_type'),
                'activity_level' => $request->input('activity_level'),
                'meals' => $request->input('meals'),
                'notes' => $request->input('notes')
            ]);

            Log::info('Nutrition plan created', [
                'plan_id' => $nutritionPlan->id,
                'coach_id' => $request->user()->id,
                'name' => $nutritionPlan->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition plan created successfully',
                'data' => $nutritionPlan
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating nutrition plan', [
                'error' => $e->getMessage(),
                'coach_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create nutrition plan'
            ], 500);
        }
    }

    /**
     * Get a single nutrition plan by ID
     * GET /api/nutrition-plans/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $nutritionPlan = NutritionPlan::with(['coach', 'assignments'])->find($id);

            if (!$nutritionPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nutrition plan not found'
                ], 404);
            }

            // Authorization: Only the coach who created the plan can view it
            if ($nutritionPlan->coach_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this nutrition plan'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $nutritionPlan
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching nutrition plan', [
                'error' => $e->getMessage(),
                'plan_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch nutrition plan'
            ], 500);
        }
    }

    /**
     * List all nutrition plans for the authenticated coach
     * GET /api/coaches/nutrition-plans
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = NutritionPlan::where('coach_id', $request->user()->id)
                ->with('assignments');

            // Optional filters
            if ($request->has('goal_type')) {
                $query->where('goal_type', $request->input('goal_type'));
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
            $nutritionPlans = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $nutritionPlans
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching nutrition plans', [
                'error' => $e->getMessage(),
                'coach_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch nutrition plans'
            ], 500);
        }
    }

    /**
     * Update an existing nutrition plan
     * PUT /api/nutrition-plans/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'duration_days' => 'nullable|integer|min:1|max:365',
            'daily_calories' => 'sometimes|required|integer|min:500|max:10000',
            'daily_protein_g' => 'sometimes|required|numeric|min:0|max:1000',
            'daily_carbs_g' => 'sometimes|required|numeric|min:0|max:2000',
            'daily_fat_g' => 'sometimes|required|numeric|min:0|max:500',
            'bmr' => 'nullable|integer|min:500|max:5000',
            'tdee' => 'nullable|integer|min:500|max:10000',
            'goal_type' => 'nullable|string|in:weight_loss,maintenance,muscle_gain',
            'activity_level' => 'nullable|string|in:sedentary,lightly_active,moderately_active,very_active,extremely_active',
            'meals' => 'nullable|array',
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
            $nutritionPlan = NutritionPlan::find($id);

            if (!$nutritionPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nutrition plan not found'
                ], 404);
            }

            // Authorization: Only the coach who created the plan can update it
            if ($nutritionPlan->coach_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this nutrition plan'
                ], 403);
            }

            $nutritionPlan->update($request->only([
                'name', 'description', 'start_date', 'duration_days',
                'daily_calories', 'daily_protein_g', 'daily_carbs_g', 'daily_fat_g',
                'bmr', 'tdee', 'goal_type', 'activity_level', 'meals', 'notes'
            ]));

            Log::info('Nutrition plan updated', [
                'plan_id' => $nutritionPlan->id,
                'coach_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition plan updated successfully',
                'data' => $nutritionPlan->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating nutrition plan', [
                'error' => $e->getMessage(),
                'plan_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update nutrition plan'
            ], 500);
        }
    }

    /**
     * Delete a nutrition plan
     * DELETE /api/nutrition-plans/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $nutritionPlan = NutritionPlan::find($id);

            if (!$nutritionPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nutrition plan not found'
                ], 404);
            }

            // Authorization: Only the coach who created the plan can delete it
            if ($nutritionPlan->coach_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this nutrition plan'
                ], 403);
            }

            $planName = $nutritionPlan->name;
            $nutritionPlan->delete();

            Log::info('Nutrition plan deleted', [
                'plan_id' => $id,
                'plan_name' => $planName,
                'coach_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition plan deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting nutrition plan', [
                'error' => $e->getMessage(),
                'plan_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete nutrition plan'
            ], 500);
        }
    }

    /**
     * Assign nutrition plan to one or more clients
     * POST /api/coaches/assign-meal-plan
     */
    public function assign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nutrition_plan_id' => 'required|integer|exists:nutrition_plans,id',
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
            $nutritionPlan = NutritionPlan::find($request->input('nutrition_plan_id'));

            // Authorization: Only the coach who created the plan can assign it
            if ($nutritionPlan->coach_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to assign this nutrition plan'
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
                $assignment = NutritionPlanAssignment::create([
                    'nutrition_plan_id' => $nutritionPlan->id,
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

            Log::info('Nutrition plan assigned to clients', [
                'plan_id' => $nutritionPlan->id,
                'coach_id' => $request->user()->id,
                'client_count' => count($clientIds)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition plan assigned successfully',
                'data' => [
                    'nutrition_plan' => $nutritionPlan,
                    'assignments' => $assignments
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error assigning nutrition plan', [
                'error' => $e->getMessage(),
                'plan_id' => $request->input('nutrition_plan_id')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign nutrition plan'
            ], 500);
        }
    }
}
