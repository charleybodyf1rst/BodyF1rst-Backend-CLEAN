<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MealPlanTemplate;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MealPlanController extends Controller
{
    /**
     * Get all meal plans with filtering and search
     */
    public function getMealPlans(Request $request)
    {
        $query = MealPlanTemplate::with(['creator:id,first_name,last_name'])
            ->orderBy('is_featured', 'desc')
            ->orderBy('use_count', 'desc')
            ->orderBy('created_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('tags', 'LIKE', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Goal filter
        if ($request->filled('goal')) {
            $query->where('goal', $request->goal);
        }

        // Duration filter
        if ($request->filled('duration_days')) {
            $query->where('duration_days', $request->duration_days);
        }

        // Calorie range filter
        if ($request->filled('min_calories')) {
            $query->where('daily_calories', '>=', $request->min_calories);
        }
        if ($request->filled('max_calories')) {
            $query->where('daily_calories', '<=', $request->max_calories);
        }

        // Public/Private filter
        if ($request->filled('is_public')) {
            $query->where('is_public', $request->is_public);
        }

        $mealPlans = $query->get();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Meal Plans Retrieved Successfully',
            'data' => $mealPlans
        ]);
    }

    /**
     * Get a specific meal plan by ID
     */
    public function getMealPlan($id)
    {
        $mealPlanTemplate = MealPlanTemplate::with(['creator:id,first_name,last_name'])->find($id);

        if (!$mealPlanTemplate) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Meal Plan Not Found'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Meal Plan Retrieved Successfully',
            'data' => $mealPlanTemplate
        ]);
    }

    /**
     * Create a new meal plan
     */
    public function createMealPlan(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'goal' => 'required|in:weight_loss,muscle_gain,maintenance,performance,health',
            'category' => 'nullable|string|max:100',
            'duration_days' => 'required|integer|min:1|max:365',
            'daily_calories' => 'required|integer|min:500|max:6000',
            'daily_protein_g' => 'required|numeric|min:0',
            'daily_carbs_g' => 'required|numeric|min:0',
            'daily_fat_g' => 'required|numeric|min:0',
            'meals_structure' => 'required|array',
            'meal_templates' => 'required|array',
            'tags' => 'nullable|array',
            'is_public' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'instructions' => 'nullable|string',
            'shopping_list' => 'nullable|array',
            'prep_tips' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $mealPlanTemplate = MealPlanTemplate::create([
            'creator_id' => $userId,
            'creator_type' => $role,
            'name' => $request->name,
            'description' => $request->description,
            'goal' => $request->goal,
            'category' => $request->category,
            'duration_days' => $request->duration_days,
            'daily_calories' => $request->daily_calories,
            'daily_protein_g' => $request->daily_protein_g,
            'daily_carbs_g' => $request->daily_carbs_g,
            'daily_fat_g' => $request->daily_fat_g,
            'meals_structure' => $request->meals_structure,
            'meal_templates' => $request->meal_templates,
            'tags' => $request->tags,
            'is_public' => $request->is_public ?? false,
            'is_featured' => $request->is_featured ?? false,
            'instructions' => $request->instructions,
            'shopping_list' => $request->shopping_list,
            'prep_tips' => $request->prep_tips,
        ]);

        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => 'Meal Plan Created Successfully',
            'data' => $mealPlanTemplate
        ], 201);
    }

    /**
     * Update an existing meal plan
     */
    public function updateMealPlan(Request $request, $id)
    {
        $mealPlanTemplate = MealPlanTemplate::find($id);

        if (!$mealPlanTemplate) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Meal Plan Not Found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'goal' => 'nullable|in:weight_loss,muscle_gain,maintenance,performance,health',
            'category' => 'nullable|string|max:100',
            'duration_days' => 'nullable|integer|min:1|max:365',
            'daily_calories' => 'nullable|integer|min:500|max:6000',
            'daily_protein_g' => 'nullable|numeric|min:0',
            'daily_carbs_g' => 'nullable|numeric|min:0',
            'daily_fat_g' => 'nullable|numeric|min:0',
            'meals_structure' => 'nullable|array',
            'meal_templates' => 'nullable|array',
            'tags' => 'nullable|array',
            'is_public' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'instructions' => 'nullable|string',
            'shopping_list' => 'nullable|array',
            'prep_tips' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $mealPlanTemplate->update($request->only([
            'name', 'description', 'goal', 'category', 'duration_days',
            'daily_calories', 'daily_protein_g', 'daily_carbs_g', 'daily_fat_g',
            'meals_structure', 'meal_templates', 'tags', 'is_public', 'is_featured',
            'instructions', 'shopping_list', 'prep_tips'
        ]));

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Meal Plan Updated Successfully',
            'data' => $mealPlanTemplate
        ]);
    }

    /**
     * Delete a meal plan
     */
    public function deleteMealPlan($id)
    {
        $mealPlanTemplate = MealPlanTemplate::find($id);

        if (!$mealPlanTemplate) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Meal Plan Not Found'
            ], 404);
        }

        // Delete all assignments first
        DB::table('meal_plan_assignments')->where('meal_plan_template_id', $id)->delete();

        $mealPlanTemplate->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Meal Plan Deleted Successfully'
        ]);
    }

    /**
     * Clone a meal plan for customization
     */
    public function cloneMealPlan(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();

        $originalPlan = MealPlanTemplate::find($id);

        if (!$originalPlan) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Meal Plan Not Found'
            ], 404);
        }

        $clonedPlan = $originalPlan->replicate();
        $clonedPlan->name = $request->name ?? ($originalPlan->name . ' (Copy)');
        $clonedPlan->creator_id = $userId;
        $clonedPlan->creator_type = $role;
        $clonedPlan->is_public = false;
        $clonedPlan->is_featured = false;
        $clonedPlan->use_count = 0;
        $clonedPlan->cloned_from = $originalPlan->id;
        $clonedPlan->save();

        // Increment use count of original
        $originalPlan->increment('use_count');

        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => 'Meal Plan Cloned Successfully',
            'data' => $clonedPlan
        ], 201);
    }

    /**
     * Assign meal plan to users and/or organizations
     */
    public function assignMealPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meal_plan_template_id' => 'required|exists:meal_plans,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'organization_ids' => 'nullable|array',
            'organization_ids.*' => 'exists:organizations,id',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $role = $request->role;
        $assignerId = Auth::guard(strtolower($role))->id();
        $mealPlanId = $request->meal_plan_template_id;
        $startDate = $request->start_date ?? Carbon::now()->toDateString();
        $notes = $request->notes;

        $assignedCount = 0;

        // Assign to individual users
        if ($request->filled('user_ids')) {
            foreach ($request->user_ids as $userId) {
                // Check if already assigned
                $exists = DB::table('meal_plan_assignments')
                    ->where('meal_plan_template_id', $mealPlanId)
                    ->where('user_id', $userId)
                    ->whereNull('organization_id')
                    ->exists();

                if (!$exists) {
                    DB::table('meal_plan_assignments')->insert([
                        'meal_plan_template_id' => $mealPlanId,
                        'user_id' => $userId,
                        'organization_id' => null,
                        'assigned_by' => $assignerId,
                        'assigner_type' => $role,
                        'start_date' => $startDate,
                        'notes' => $notes,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                    $assignedCount++;
                }
            }
        }

        // Assign to organizations
        if ($request->filled('organization_ids')) {
            foreach ($request->organization_ids as $orgId) {
                // Check if already assigned
                $exists = DB::table('meal_plan_assignments')
                    ->where('meal_plan_template_id', $mealPlanId)
                    ->where('organization_id', $orgId)
                    ->whereNull('user_id')
                    ->exists();

                if (!$exists) {
                    DB::table('meal_plan_assignments')->insert([
                        'meal_plan_template_id' => $mealPlanId,
                        'user_id' => null,
                        'organization_id' => $orgId,
                        'assigned_by' => $assignerId,
                        'assigner_type' => $role,
                        'start_date' => $startDate,
                        'notes' => $notes,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                    $assignedCount++;
                }
            }
        }

        // Increment use count
        if ($assignedCount > 0) {
            MealPlanTemplate::where('id', $mealPlanId)->increment('use_count', $assignedCount);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => "Meal Plan Assigned Successfully to {$assignedCount} recipient(s)",
            'assigned_count' => $assignedCount
        ]);
    }

    /**
     * Unassign meal plan from user or organization
     */
    public function unassignMealPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meal_plan_template_id' => 'required|exists:meal_plans,id',
            'user_id' => 'nullable|exists:users,id',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $query = DB::table('meal_plan_assignments')
            ->where('meal_plan_template_id', $request->meal_plan_template_id);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $deleted = $query->delete();

        if ($deleted > 0) {
            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Meal Plan Unassigned Successfully'
            ]);
        }

        return response()->json([
            'status' => 404,
            'success' => false,
            'message' => 'Assignment Not Found'
        ], 404);
    }

    /**
     * Get assigned meal plans for a user
     */
    public function getUserMealPlans(Request $request, $userId)
    {
        // Get direct assignments
        $directAssignments = DB::table('meal_plan_assignments')
            ->where('user_id', $userId)
            ->whereNull('organization_id')
            ->pluck('meal_plan_template_id');

        // Get organization assignments
        $userOrgs = DB::table('organization_employees')
            ->where('employee_id', $userId)
            ->pluck('organization_id');

        $orgAssignments = DB::table('meal_plan_assignments')
            ->whereIn('organization_id', $userOrgs)
            ->whereNull('user_id')
            ->pluck('meal_plan_template_id');

        // Combine and get unique meal plan IDs
        $allMealPlanIds = $directAssignments->merge($orgAssignments)->unique();

        $mealPlans = MealPlanTemplate::whereIn('id', $allMealPlanIds)
            ->with(['creator:id,first_name,last_name'])
            ->get();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'User Meal Plans Retrieved Successfully',
            'data' => $mealPlans
        ]);
    }

    /**
     * Get meal plans assigned to an organization
     */
    public function getOrganizationMealPlans($orgId)
    {
        $mealPlanIds = DB::table('meal_plan_assignments')
            ->where('organization_id', $orgId)
            ->whereNull('user_id')
            ->pluck('meal_plan_template_id');

        $mealPlans = MealPlanTemplate::whereIn('id', $mealPlanIds)
            ->with(['creator:id,first_name,last_name'])
            ->get();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Organization Meal Plans Retrieved Successfully',
            'data' => $mealPlans
        ]);
    }

    /**
     * Get all meal plan templates (alias for getMealPlans)
     */
    public function getMealPlanTemplates(Request $request)
    {
        return $this->getMealPlans($request);
    }

    /**
     * Add a new meal plan template (alias for createMealPlan)
     */
    public function addMealPlanTemplate(Request $request)
    {
        return $this->createMealPlan($request);
    }

    /**
     * Update a meal plan template (alias for updateMealPlan)
     */
    public function updateMealPlanTemplate(Request $request, $id)
    {
        return $this->updateMealPlan($request, $id);
    }

    /**
     * Get a meal plan template by ID (alias for getMealPlan)
     */
    public function getMealPlanTemplateById($id)
    {
        return $this->getMealPlan($id);
    }

    /**
     * Assign meal plan template to a user
     */
    public function assignMealPlanTemplate(Request $request)
    {
        return $this->assignMealPlan($request);
    }

    /**
     * Get assigned meal plans for a specific user
     */
    public function getAssignedMealPlans($userId)
    {
        return $this->getUserMealPlans($userId);
    }

    /**
     * Clone a meal plan template (alias for cloneMealPlan)
     */
    public function cloneMealPlanTemplate($id)
    {
        return $this->cloneMealPlan($id);
    }
}
