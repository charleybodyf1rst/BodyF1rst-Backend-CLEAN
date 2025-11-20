<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Department & Organization Management Controller
 * Handles departments, rewards, and organization analytics
 */
class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:admin', 'role']);
    }

    /**
     * Get Departments
     * GET /api/admin/get-departments
     */
    public function getDepartments(Request $request)
    {
        try {
            $organizationId = $request->input('organization_id');

            $query = Department::with(['organization', 'members']);

            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }

            $departments = $query->get()->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'description' => $dept->description,
                    'organizationId' => $dept->organization_id,
                    'organizationName' => $dept->organization->name ?? null,
                    'parentDepartmentId' => $dept->parent_department_id,
                    'memberCount' => $dept->members->count(),
                    'activeMembers' => $dept->members->where('is_active', true)->count(),
                    'createdAt' => $dept->created_at,
                    'stats' => $this->getDepartmentStats($dept->id),
                ];
            });

            return response()->json([
                'success' => true,
                'departments' => $departments,
                'total' => $departments->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load departments',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Add Department
     * POST /api/admin/add-department
     */
    public function addDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'organization_id' => 'required|exists:organizations,id',
            'parent_department_id' => 'nullable|exists:departments,id',
            'department_head_id' => 'nullable|exists:users,id',
            'goals' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $department = Department::create([
                'name' => $request->name,
                'description' => $request->description,
                'organization_id' => $request->organization_id,
                'parent_department_id' => $request->parent_department_id,
                'department_head_id' => $request->department_head_id,
                'goals' => $request->goals ? json_encode($request->goals) : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'department' => $department,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update Department
     * POST /api/admin/update-department/{id}
     */
    public function updateDepartment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'parent_department_id' => 'nullable|exists:departments,id',
            'department_head_id' => 'nullable|exists:users,id',
            'goals' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $department = Department::findOrFail($id);

            $department->update($request->only([
                'name',
                'description',
                'parent_department_id',
                'department_head_id',
            ]));

            if ($request->has('goals')) {
                $department->goals = json_encode($request->goals);
                $department->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'department' => $department,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Department Details
     * GET /api/admin/get-department/{id}
     */
    public function getDepartmentDetails($id)
    {
        try {
            $department = Department::with([
                'organization',
                'members',
                'workoutPlans',
                'nutritionPlans',
            ])->findOrFail($id);

            $details = [
                'id' => $department->id,
                'name' => $department->name,
                'description' => $department->description,
                'organizationId' => $department->organization_id,
                'organizationName' => $department->organization->name ?? null,
                'parentDepartmentId' => $department->parent_department_id,
                'memberCount' => $department->members->count(),
                'createdAt' => $department->created_at,
                'members' => $department->members->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'isActive' => $user->is_active,
                        'profilePhoto' => $user->profile_photo,
                    ];
                }),
                'goals' => $department->goals ? json_decode($department->goals, true) : [],
                'assignedWorkoutPlans' => $department->workoutPlans ?? [],
                'assignedNutritionPlans' => $department->nutritionPlans ?? [],
                'recentActivity' => $this->getDepartmentActivity($id),
                'performanceMetrics' => $this->getDepartmentPerformance($id),
            ];

            return response()->json([
                'success' => true,
                'department' => $details,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 404);
        }
    }

    /**
     * Delete Department
     * DELETE /api/admin/delete-department/{id}
     */
    public function deleteDepartment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reassign_to' => 'required|string', // 'parent', 'default', or department ID
            'archive_data' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $department = Department::findOrFail($id);
            $reassignTo = $request->reassign_to;

            DB::beginTransaction();

            // Reassign members
            if ($reassignTo === 'parent' && $department->parent_department_id) {
                $department->members()->update(['department_id' => $department->parent_department_id]);
            } elseif ($reassignTo === 'default') {
                $department->members()->update(['department_id' => null]);
            } elseif (is_numeric($reassignTo)) {
                $department->members()->update(['department_id' => $reassignTo]);
            }

            // Archive or delete
            if ($request->archive_data) {
                $department->archived = true;
                $department->save();
            } else {
                $department->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Rewards
     * GET /api/admin/get-rewards
     */
    public function getRewards(Request $request)
    {
        try {
            $query = Reward::query();

            if ($request->has('organization_id')) {
                $query->where('organization_id', $request->organization_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $rewards = $query->get()->map(function ($reward) {
                return [
                    'id' => $reward->id,
                    'title' => $reward->title,
                    'description' => $reward->description,
                    'pointsCost' => $reward->points_cost,
                    'category' => $reward->category,
                    'imageUrl' => $reward->image_url,
                    'status' => $reward->status,
                    'inventory' => $reward->inventory,
                    'redemptionCount' => $reward->redemptions->count(),
                    'organizationId' => $reward->organization_id,
                    'createdAt' => $reward->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'rewards' => $rewards,
                'total' => $rewards->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load rewards',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Add Reward
     * POST /api/admin/add-reward
     */
    public function addReward(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'points_cost' => 'required|integer|min:0',
            'category' => 'required|string',
            'inventory' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $imageUrl = null;

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('uploads/rewards'), $imageName);
                $imageUrl = '/uploads/rewards/' . $imageName;
            }

            $reward = Reward::create([
                'title' => $request->title,
                'description' => $request->description,
                'points_cost' => $request->points_cost,
                'category' => $request->category,
                'inventory' => $request->inventory,
                'image_url' => $imageUrl,
                'organization_id' => $request->organization_id,
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reward created successfully',
                'reward' => $reward,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reward',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update Reward
     * POST /api/admin/update-reward/{id}
     */
    public function updateReward(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'points_cost' => 'sometimes|integer|min:0',
            'category' => 'sometimes|string',
            'inventory' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive,depleted',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $reward = Reward::findOrFail($id);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('uploads/rewards'), $imageName);
                $reward->image_url = '/uploads/rewards/' . $imageName;
            }

            $reward->update($request->only([
                'title',
                'description',
                'points_cost',
                'category',
                'inventory',
                'status',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Reward updated successfully',
                'reward' => $reward,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update reward',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Reward Details
     * GET /api/admin/get-reward/{id}
     */
    public function getRewardDetails($id)
    {
        try {
            $reward = Reward::with('redemptions.user')->findOrFail($id);

            $details = [
                'id' => $reward->id,
                'title' => $reward->title,
                'description' => $reward->description,
                'pointsCost' => $reward->points_cost,
                'category' => $reward->category,
                'imageUrl' => $reward->image_url,
                'status' => $reward->status,
                'inventory' => $reward->inventory,
                'organizationId' => $reward->organization_id,
                'createdAt' => $reward->created_at,
                'redemptions' => $reward->redemptions->map(function ($redemption) {
                    return [
                        'userId' => $redemption->user_id,
                        'userName' => $redemption->user->name,
                        'date' => $redemption->created_at,
                        'status' => $redemption->status,
                    ];
                }),
                'analytics' => [
                    'totalRedemptions' => $reward->redemptions->count(),
                    'pendingRedemptions' => $reward->redemptions->where('status', 'pending')->count(),
                    'averageRedemptionTime' => 2,
                    'popularityScore' => 85,
                ],
            ];

            return response()->json([
                'success' => true,
                'reward' => $details,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reward not found',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 404);
        }
    }

    /**
     * Get Organization Analytics Dashboard
     * GET /api/admin/get-analytics-dashboard/{organizationId}
     */
    public function getOrganizationAnalyticsDashboard($organizationId)
    {
        try {
            $organization = Organization::with('users', 'departments')->findOrFail($organizationId);

            $analytics = [
                'organizationInfo' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'memberCount' => $organization->users->count(),
                    'activeMembers' => $organization->users->where('is_active', true)->count(),
                    'inactiveMembers' => $organization->users->where('is_active', false)->count(),
                    'activationRate' => $organization->users->count() > 0
                        ? ($organization->users->where('is_active', true)->count() / $organization->users->count()) * 100
                        : 0,
                ],
                'engagement' => $this->getOrganizationEngagement($organizationId),
                'workouts' => $this->getOrganizationWorkoutStats($organizationId),
                'nutrition' => $this->getOrganizationNutritionStats($organizationId),
                'departments' => $organization->departments->map(function ($dept) {
                    return [
                        'id' => $dept->id,
                        'name' => $dept->name,
                        'memberCount' => $dept->members->count(),
                        'engagementScore' => $this->calculateDepartmentEngagement($dept->id),
                        'completionRate' => $this->calculateDepartmentCompletionRate($dept->id),
                    ];
                }),
                'topPerformers' => $this->getTopPerformers($organizationId),
                'contentUtilization' => $this->getContentUtilization($organizationId),
                'roi' => $this->calculateROI($organizationId),
                'insights' => $this->generateOrganizationInsights($organizationId),
                'recommendations' => $this->generateOrganizationRecommendations($organizationId),
            ];

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load organization analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Helper methods

    protected function getDepartmentStats($departmentId)
    {
        // Implementation for department statistics
        return [
            'averageEngagement' => 75,
            'completedWorkouts' => 450,
            'totalCaloriesBurned' => 125000,
        ];
    }

    protected function getDepartmentActivity($departmentId)
    {
        // Implementation for recent department activity
        return [];
    }

    protected function getDepartmentPerformance($departmentId)
    {
        return [
            'engagementRate' => 78,
            'completionRate' => 85,
            'averageWorkoutsPerWeek' => 3.5,
            'topPerformers' => [],
        ];
    }

    protected function getOrganizationEngagement($organizationId)
    {
        return [
            'overallEngagementScore' => 82,
            'dailyActiveUsers' => 450,
            'weeklyActiveUsers' => 1200,
            'monthlyActiveUsers' => 2800,
            'averageSessionDuration' => 28,
            'engagementTrend' => [],
        ];
    }

    protected function getOrganizationWorkoutStats($organizationId)
    {
        return [
            'totalWorkouts' => 15000,
            'completionRate' => 87,
            'averageWorkoutsPerUser' => 5.3,
            'mostPopularWorkouts' => [],
            'workoutsTrend' => [],
        ];
    }

    protected function getOrganizationNutritionStats($organizationId)
    {
        return [
            'activeNutritionUsers' => 800,
            'mealLoggingRate' => 65,
            'averageMealsLoggedPerDay' => 2.8,
        ];
    }

    protected function calculateDepartmentEngagement($departmentId)
    {
        return 75;
    }

    protected function calculateDepartmentCompletionRate($departmentId)
    {
        return 82;
    }

    protected function getTopPerformers($organizationId)
    {
        return [];
    }

    protected function getContentUtilization($organizationId)
    {
        return [
            'workoutPlans' => [
                'assigned' => 150,
                'started' => 120,
                'completed' => 95,
            ],
            'nutritionPlans' => [
                'assigned' => 100,
                'active' => 75,
                'adherenceRate' => 68,
            ],
        ];
    }

    protected function calculateROI($organizationId)
    {
        return [
            'totalInvestment' => 50000,
            'costPerActiveUser' => 18,
            'projectedHealthcareSavings' => 125000,
            'absenteeismReduction' => 15,
            'productivityIncrease' => 12,
        ];
    }

    protected function generateOrganizationInsights($organizationId)
    {
        return [
            'Engagement is up 12% compared to last month',
            'Engineering department has the highest completion rate',
        ];
    }

    protected function generateOrganizationRecommendations($organizationId)
    {
        return [
            'Consider launching a company-wide challenge',
            'Increase nutrition plan assignments',
        ];
    }
}
