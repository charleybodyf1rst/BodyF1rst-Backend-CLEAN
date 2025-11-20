<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\MealTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MealTemplateController extends Controller
{
    /**
     * Get all meal templates available to the coach
     * (Own templates + public templates)
     */
    public function index(Request $request)
    {
        try {
            $coach = Auth::user();
            $mealType = $request->query('meal_type');
            $category = $request->query('category');
            $search = $request->query('search');

            $query = MealTemplate::availableToCoach($coach->id)
                ->with('coach:id,name');

            // Filter by meal type if provided
            if ($mealType) {
                $query->where('meal_type', $mealType);
            }

            // Filter by category if provided
            if ($category) {
                $query->where('category', $category);
            }

            // Search by name if provided
            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            $templates = $query->orderBy('use_count', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($template) {
                    return [
                        'id' => $template->id,
                        'name' => $template->name,
                        'description' => $template->description,
                        'meal_type' => $template->meal_type,
                        'total_calories' => $template->total_calories,
                        'total_protein_g' => $template->total_protein_g,
                        'total_carbs_g' => $template->total_carbs_g,
                        'total_fat_g' => $template->total_fat_g,
                        'macro_percentages' => $template->macro_percentages,
                        'category' => $template->category,
                        'tags' => $template->tags,
                        'prep_time_minutes' => $template->prep_time_minutes,
                        'cook_time_minutes' => $template->cook_time_minutes,
                        'total_time' => $template->total_time,
                        'use_count' => $template->use_count,
                        'is_public' => $template->is_public,
                        'coach_name' => $template->coach->name ?? 'System',
                        'image_url' => $template->image_url,
                        'food_count' => count($template->foods ?? [])
                    ];
                });

            return response([
                "status" => 200,
                "message" => "Meal templates retrieved successfully",
                "templates" => $templates,
                "total" => $templates->count()
            ], 200);

        } catch (\Exception $e) {
            Log::error('[Meal Template] Failed to fetch templates', [
                'error' => $e->getMessage()
            ]);

            return response([
                "status" => 500,
                "message" => "Failed to retrieve meal templates",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific meal template by ID
     */
    public function show($id)
    {
        try {
            $template = MealTemplate::with('coach:id,name')->find($id);

            if (!$template) {
                return response([
                    "status" => 404,
                    "message" => "Meal template not found"
                ], 404);
            }

            return response([
                "status" => 200,
                "message" => "Meal template retrieved successfully",
                "template" => [
                    'id' => $template->id,
                    'coach' => [
                        'id' => $template->coach->id,
                        'name' => $template->coach->name
                    ],
                    'name' => $template->name,
                    'description' => $template->description,
                    'meal_type' => $template->meal_type,
                    'foods' => $template->foods,
                    'total_calories' => $template->total_calories,
                    'total_protein_g' => $template->total_protein_g,
                    'total_carbs_g' => $template->total_carbs_g,
                    'total_fat_g' => $template->total_fat_g,
                    'total_fiber_g' => $template->total_fiber_g,
                    'macro_percentages' => $template->macro_percentages,
                    'category' => $template->category,
                    'tags' => $template->tags,
                    'prep_time_minutes' => $template->prep_time_minutes,
                    'cook_time_minutes' => $template->cook_time_minutes,
                    'total_time' => $template->total_time,
                    'instructions' => $template->instructions,
                    'image_url' => $template->image_url,
                    'use_count' => $template->use_count,
                    'is_public' => $template->is_public,
                    'created_at' => $template->created_at->toIso8601String()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('[Meal Template] Failed to fetch template', [
                'template_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response([
                "status" => 500,
                "message" => "Failed to retrieve meal template",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new meal template
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
            'foods' => 'required|array|min:1',
            'foods.*.food_name' => 'required|string',
            'foods.*.serving_size' => 'required',
            'foods.*.serving_unit' => 'required|string',
            'foods.*.quantity' => 'required|numeric|min:0.1',
            'foods.*.calories' => 'required|numeric|min:0',
            'foods.*.protein' => 'required|numeric|min:0',
            'foods.*.carbs' => 'required|numeric|min:0',
            'foods.*.fat' => 'required|numeric|min:0',
            'foods.*.fiber' => 'nullable|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'cook_time_minutes' => 'nullable|integer|min:0',
            'instructions' => 'nullable|string',
            'image_url' => 'nullable|url',
            'is_public' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response([
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        try {
            $coach = Auth::user();

            Log::info('[Meal Template] Creating template', [
                'coach_id' => $coach->id,
                'template_name' => $request->name
            ]);

            // Create template
            $template = new MealTemplate($request->only([
                'name', 'description', 'meal_type', 'foods',
                'category', 'tags', 'prep_time_minutes', 'cook_time_minutes',
                'instructions', 'image_url', 'is_public'
            ]));

            $template->coach_id = $coach->id;

            // Calculate totals from foods
            $template->calculateTotals();
            $template->save();

            Log::info('[Meal Template] Template created', [
                'template_id' => $template->id,
                'calories' => $template->total_calories
            ]);

            return response([
                "status" => 200,
                "message" => "Meal template created successfully",
                "template" => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'meal_type' => $template->meal_type,
                    'total_calories' => $template->total_calories,
                    'total_protein_g' => $template->total_protein_g,
                    'total_carbs_g' => $template->total_carbs_g,
                    'total_fat_g' => $template->total_fat_g,
                    'created_at' => $template->created_at->toIso8601String()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('[Meal Template] Failed to create template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response([
                "status" => 500,
                "message" => "Failed to create meal template",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing meal template
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'meal_type' => 'nullable|in:breakfast,lunch,dinner,snack',
            'foods' => 'nullable|array|min:1',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'cook_time_minutes' => 'nullable|integer|min:0',
            'instructions' => 'nullable|string',
            'image_url' => 'nullable|url',
            'is_public' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response([
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        try {
            $coach = Auth::user();
            $template = MealTemplate::find($id);

            if (!$template) {
                return response([
                    "status" => 404,
                    "message" => "Meal template not found"
                ], 404);
            }

            // Verify ownership
            if ($template->coach_id !== $coach->id) {
                return response([
                    "status" => 403,
                    "message" => "You don't have permission to update this template"
                ], 403);
            }

            // Update fields
            $template->fill($request->only([
                'name', 'description', 'meal_type', 'foods',
                'category', 'tags', 'prep_time_minutes', 'cook_time_minutes',
                'instructions', 'image_url', 'is_public'
            ]));

            // Recalculate totals if foods changed
            if ($request->has('foods')) {
                $template->calculateTotals();
            }

            $template->save();

            Log::info('[Meal Template] Template updated', ['template_id' => $template->id]);

            return response([
                "status" => 200,
                "message" => "Meal template updated successfully",
                "template" => $template
            ], 200);

        } catch (\Exception $e) {
            Log::error('[Meal Template] Failed to update template', [
                'template_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response([
                "status" => 500,
                "message" => "Failed to update meal template",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a meal template
     */
    public function destroy($id)
    {
        try {
            $coach = Auth::user();
            $template = MealTemplate::find($id);

            if (!$template) {
                return response([
                    "status" => 404,
                    "message" => "Meal template not found"
                ], 404);
            }

            // Verify ownership
            if ($template->coach_id !== $coach->id) {
                return response([
                    "status" => 403,
                    "message" => "You don't have permission to delete this template"
                ], 403);
            }

            $template->delete();

            Log::info('[Meal Template] Template deleted', ['template_id' => $id]);

            return response([
                "status" => 200,
                "message" => "Meal template deleted successfully"
            ], 200);

        } catch (\Exception $e) {
            Log::error('[Meal Template] Failed to delete template', [
                'template_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response([
                "status" => 500,
                "message" => "Failed to delete meal template",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate an existing template
     */
    public function duplicate($id)
    {
        try {
            $coach = Auth::user();
            $original = MealTemplate::find($id);

            if (!$original) {
                return response([
                    "status" => 404,
                    "message" => "Meal template not found"
                ], 404);
            }

            // Create duplicate
            $duplicate = $original->replicate();
            $duplicate->coach_id = $coach->id;
            $duplicate->name = $original->name . ' (Copy)';
            $duplicate->use_count = 0;
            $duplicate->is_public = false;
            $duplicate->save();

            // Increment use count of original if it's public
            if ($original->is_public && $original->coach_id !== $coach->id) {
                $original->incrementUseCount();
            }

            Log::info('[Meal Template] Template duplicated', [
                'original_id' => $id,
                'duplicate_id' => $duplicate->id
            ]);

            return response([
                "status" => 200,
                "message" => "Meal template duplicated successfully",
                "template" => $duplicate
            ], 200);

        } catch (\Exception $e) {
            Log::error('[Meal Template] Failed to duplicate template', [
                'template_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response([
                "status" => 500,
                "message" => "Failed to duplicate meal template",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a meal template to a specific day in a nutrition plan
     */
    public function assignToPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nutrition_plan_id' => 'required|integer|exists:nutrition_plans,id',
            'meal_template_id' => 'required|integer|exists:meal_templates,id',
            'day_number' => 'required|integer|min:1',
            'meal_slot' => 'required|in:breakfast,lunch,dinner,snack'
        ]);

        if ($validator->fails()) {
            return response([
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        try {
            // Create or update assignment
            $assignment = \DB::table('meal_template_plan_assignments')
                ->updateOrInsert(
                    [
                        'nutrition_plan_id' => $request->nutrition_plan_id,
                        'day_number' => $request->day_number,
                        'meal_slot' => $request->meal_slot
                    ],
                    [
                        'meal_template_id' => $request->meal_template_id,
                        'updated_at' => now()
                    ]
                );

            // Increment use count
            $template = MealTemplate::find($request->meal_template_id);
            if ($template) {
                $template->incrementUseCount();
            }

            Log::info('[Meal Template] Template assigned to plan', [
                'plan_id' => $request->nutrition_plan_id,
                'template_id' => $request->meal_template_id,
                'day' => $request->day_number,
                'slot' => $request->meal_slot
            ]);

            return response([
                "status" => 200,
                "message" => "Meal template assigned successfully"
            ], 200);

        } catch (\Exception $e) {
            Log::error('[Meal Template] Failed to assign template', [
                'error' => $e->getMessage()
            ]);

            return response([
                "status" => 500,
                "message" => "Failed to assign meal template",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get meal templates assigned to a nutrition plan
     */
    public function getPlanMealTemplates($planId)
    {
        try {
            $assignments = \DB::table('meal_template_plan_assignments')
                ->join('meal_templates', 'meal_templates.id', '=', 'meal_template_plan_assignments.meal_template_id')
                ->where('nutrition_plan_id', $planId)
                ->select(
                    'meal_template_plan_assignments.*',
                    'meal_templates.name',
                    'meal_templates.meal_type',
                    'meal_templates.total_calories',
                    'meal_templates.total_protein_g',
                    'meal_templates.total_carbs_g',
                    'meal_templates.total_fat_g'
                )
                ->orderBy('day_number')
                ->orderBy('meal_slot')
                ->get();

            return response([
                "status" => 200,
                "message" => "Plan meal templates retrieved successfully",
                "assignments" => $assignments
            ], 200);

        } catch (\Exception $e) {
            Log::error('[Meal Template] Failed to fetch plan templates', [
                'plan_id' => $planId,
                'error' => $e->getMessage()
            ]);

            return response([
                "status" => 500,
                "message" => "Failed to retrieve plan meal templates",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
