<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\WorkoutLibrary;
use App\Models\NutritionPlanLibrary;
use App\Models\ChallengeLibrary;
use App\Models\FitnessVideosLibrary;
use App\Models\NutritionVideosLibrary;
use App\Models\MindsetVideosLibrary;
use App\Models\NotificationVideosLibrary;
use App\Models\WorkoutPlan;
use App\Models\NutritionPlan;
use App\Models\CoachChallenge;
use App\Models\CoachFitnessVideo;
use App\Models\CoachNutritionVideo;
use App\Models\CoachMindsetVideo;
use App\Models\CoachNotificationVideo;

class AdminLibraryController extends Controller
{
    /**
     * Verify admin authorization
     */
    private function verifyAdmin()
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin || $admin->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized - Admin access required'], 403);
        }
        return $admin;
    }

    // ============================================
    // WORKOUT LIBRARY - ADMIN CRUD
    // ============================================

    public function getWorkoutLibrary(Request $request)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $workouts = WorkoutLibrary::with('creator:id,first_name,last_name,email')
                ->when($request->search, fn($q, $search) =>
                    $q->where('name', 'like', "%{$search}%")
                )
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->when($request->difficulty, fn($q, $difficulty) => $q->difficulty($difficulty))
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json(['success' => true, 'workouts' => $workouts]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch workouts', 'error' => $e->getMessage()], 500);
        }
    }

    public function storeWorkoutLibrary(Request $request)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|string',
                'difficulty_level' => 'nullable|string',
                'goal' => 'nullable|string',
                'duration_weeks' => 'integer|min:1',
                'sessions_per_week' => 'integer|min:1',
                'exercises' => 'nullable|array',
                'tags' => 'nullable|array',
                'thumbnail_url' => 'nullable|string',
                'is_featured' => 'boolean',
                'notes' => 'nullable|string'
            ]);

            $validated['created_by_admin_id'] = $admin->id;
            $workout = WorkoutLibrary::create($validated);

            return response()->json(['success' => true, 'message' => 'Workout created successfully', 'workout' => $workout], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create workout', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateWorkoutLibrary(Request $request, $id)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $workout = WorkoutLibrary::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|string',
                'difficulty_level' => 'nullable|string',
                'goal' => 'nullable|string',
                'duration_weeks' => 'integer|min:1',
                'sessions_per_week' => 'integer|min:1',
                'exercises' => 'nullable|array',
                'tags' => 'nullable|array',
                'thumbnail_url' => 'nullable|string',
                'is_featured' => 'boolean',
                'notes' => 'nullable|string'
            ]);

            $workout->update($validated);

            return response()->json(['success' => true, 'message' => 'Workout updated successfully', 'workout' => $workout]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update workout', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteWorkoutLibrary($id)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $workout = WorkoutLibrary::findOrFail($id);
            $workout->delete();

            return response()->json(['success' => true, 'message' => 'Workout deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete workout', 'error' => $e->getMessage()], 500);
        }
    }

    // ============================================
    // NUTRITION PLAN LIBRARY - ADMIN CRUD
    // ============================================

    public function getNutritionPlanLibrary(Request $request)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $plans = NutritionPlanLibrary::with('creator:id,first_name,last_name,email')
                ->when($request->search, fn($q, $search) =>
                    $q->where('name', 'like', "%{$search}%")
                )
                ->when($request->goal_type, fn($q, $goalType) => $q->goalType($goalType))
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json(['success' => true, 'plans' => $plans]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch nutrition plans', 'error' => $e->getMessage()], 500);
        }
    }

    public function storeNutritionPlanLibrary(Request $request)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'duration_days' => 'required|integer|min:1',
                'daily_calories' => 'required|integer',
                'daily_protein_g' => 'required|numeric',
                'daily_carbs_g' => 'required|numeric',
                'daily_fat_g' => 'required|numeric',
                'goal_type' => 'nullable|string',
                'activity_level' => 'nullable|string',
                'meals' => 'nullable|array',
                'tags' => 'nullable|array',
                'thumbnail_url' => 'nullable|string',
                'is_featured' => 'boolean',
                'notes' => 'nullable|string'
            ]);

            $validated['created_by_admin_id'] = $admin->id;
            $plan = NutritionPlanLibrary::create($validated);

            return response()->json(['success' => true, 'message' => 'Nutrition plan created successfully', 'plan' => $plan], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create nutrition plan', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateNutritionPlanLibrary(Request $request, $id)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $plan = NutritionPlanLibrary::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'duration_days' => 'integer|min:1',
                'daily_calories' => 'integer',
                'daily_protein_g' => 'numeric',
                'daily_carbs_g' => 'numeric',
                'daily_fat_g' => 'numeric',
                'goal_type' => 'nullable|string',
                'activity_level' => 'nullable|string',
                'meals' => 'nullable|array',
                'tags' => 'nullable|array',
                'thumbnail_url' => 'nullable|string',
                'is_featured' => 'boolean',
                'notes' => 'nullable|string'
            ]);

            $plan->update($validated);

            return response()->json(['success' => true, 'message' => 'Nutrition plan updated successfully', 'plan' => $plan]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update nutrition plan', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteNutritionPlanLibrary($id)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $plan = NutritionPlanLibrary::findOrFail($id);
            $plan->delete();

            return response()->json(['success' => true, 'message' => 'Nutrition plan deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete nutrition plan', 'error' => $e->getMessage()], 500);
        }
    }

    // ============================================
    // CHALLENGE LIBRARY - ADMIN CRUD
    // ============================================

    public function getChallengeLibrary(Request $request)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $challenges = ChallengeLibrary::with('creator:id,first_name,last_name,email')
                ->when($request->search, fn($q, $search) =>
                    $q->where('name', 'like', "%{$search}%")
                )
                ->when($request->challenge_type, fn($q, $type) => $q->challengeType($type))
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json(['success' => true, 'challenges' => $challenges]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch challenges', 'error' => $e->getMessage()], 500);
        }
    }

    public function storeChallengeLibrary(Request $request)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'challenge_type' => 'required|string',
                'duration_days' => 'required|integer|min:1',
                'daily_tasks' => 'nullable|array',
                'rules' => 'nullable|array',
                'rewards' => 'nullable|array',
                'thumbnail_url' => 'nullable|string',
                'is_featured' => 'boolean',
                'notes' => 'nullable|string'
            ]);

            $validated['created_by_admin_id'] = $admin->id;
            $challenge = ChallengeLibrary::create($validated);

            return response()->json(['success' => true, 'message' => 'Challenge created successfully', 'challenge' => $challenge], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create challenge', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateChallengeLibrary(Request $request, $id)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $challenge = ChallengeLibrary::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'challenge_type' => 'string',
                'duration_days' => 'integer|min:1',
                'daily_tasks' => 'nullable|array',
                'rules' => 'nullable|array',
                'rewards' => 'nullable|array',
                'thumbnail_url' => 'nullable|string',
                'is_featured' => 'boolean',
                'notes' => 'nullable|string'
            ]);

            $challenge->update($validated);

            return response()->json(['success' => true, 'message' => 'Challenge updated successfully', 'challenge' => $challenge]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update challenge', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteChallengeLibrary($id)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $challenge = ChallengeLibrary::findOrFail($id);
            $challenge->delete();

            return response()->json(['success' => true, 'message' => 'Challenge deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete challenge', 'error' => $e->getMessage()], 500);
        }
    }

    // ============================================
    // VIDEO LIBRARIES - ADMIN CRUD (Generic)
    // ============================================

    public function getVideoLibrary(Request $request, $type)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $modelMap = [
                'fitness' => FitnessVideosLibrary::class,
                'nutrition' => NutritionVideosLibrary::class,
                'mindset' => MindsetVideosLibrary::class,
                'notification' => NotificationVideosLibrary::class,
            ];

            if (!isset($modelMap[$type])) {
                return response()->json(['success' => false, 'message' => 'Invalid video type'], 400);
            }

            $videos = $modelMap[$type]::with('creator:id,first_name,last_name,email')
                ->when($request->search, fn($q, $search) =>
                    $q->where('title', 'like', "%{$search}%")
                )
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json(['success' => true, 'videos' => $videos]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch videos', 'error' => $e->getMessage()], 500);
        }
    }

    public function storeVideoLibrary(Request $request, $type)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $modelMap = [
                'fitness' => FitnessVideosLibrary::class,
                'nutrition' => NutritionVideosLibrary::class,
                'mindset' => MindsetVideosLibrary::class,
                'notification' => NotificationVideosLibrary::class,
            ];

            if (!isset($modelMap[$type])) {
                return response()->json(['success' => false, 'message' => 'Invalid video type'], 400);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'video_url' => 'required|string',
                'thumbnail_url' => 'nullable|string',
                'duration_seconds' => 'nullable|integer',
                'category' => 'nullable|string',
                'difficulty_level' => 'nullable|string',
                'tags' => 'nullable|array',
                'is_featured' => 'boolean',
                'notes' => 'nullable|string'
            ]);

            $validated['created_by_admin_id'] = $admin->id;
            $video = $modelMap[$type]::create($validated);

            return response()->json(['success' => true, 'message' => 'Video created successfully', 'video' => $video], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create video', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateVideoLibrary(Request $request, $type, $id)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $modelMap = [
                'fitness' => FitnessVideosLibrary::class,
                'nutrition' => NutritionVideosLibrary::class,
                'mindset' => MindsetVideosLibrary::class,
                'notification' => NotificationVideosLibrary::class,
            ];

            if (!isset($modelMap[$type])) {
                return response()->json(['success' => false, 'message' => 'Invalid video type'], 400);
            }

            $video = $modelMap[$type]::findOrFail($id);

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'video_url' => 'string',
                'thumbnail_url' => 'nullable|string',
                'duration_seconds' => 'nullable|integer',
                'category' => 'nullable|string',
                'difficulty_level' => 'nullable|string',
                'tags' => 'nullable|array',
                'is_featured' => 'boolean',
                'notes' => 'nullable|string'
            ]);

            $video->update($validated);

            return response()->json(['success' => true, 'message' => 'Video updated successfully', 'video' => $video]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update video', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteVideoLibrary($type, $id)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $modelMap = [
                'fitness' => FitnessVideosLibrary::class,
                'nutrition' => NutritionVideosLibrary::class,
                'mindset' => MindsetVideosLibrary::class,
                'notification' => NotificationVideosLibrary::class,
            ];

            if (!isset($modelMap[$type])) {
                return response()->json(['success' => false, 'message' => 'Invalid video type'], 400);
            }

            $video = $modelMap[$type]::findOrFail($id);
            $video->delete();

            return response()->json(['success' => true, 'message' => 'Video deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete video', 'error' => $e->getMessage()], 500);
        }
    }

    // ============================================
    // ADMIN VIEW ALL PRIVATE LIBRARIES
    // ============================================

    public function getAllCoachPrivateContent(Request $request, $type)
    {
        $admin = $this->verifyAdmin();
        if ($admin instanceof \Illuminate\Http\JsonResponse) return $admin;

        try {
            $modelMap = [
                'workouts' => WorkoutPlan::class,
                'nutrition_plans' => NutritionPlan::class,
                'challenges' => CoachChallenge::class,
                'fitness_videos' => CoachFitnessVideo::class,
                'nutrition_videos' => CoachNutritionVideo::class,
                'mindset_videos' => CoachMindsetVideo::class,
                'notification_videos' => CoachNotificationVideo::class,
            ];

            if (!isset($modelMap[$type])) {
                return response()->json(['success' => false, 'message' => 'Invalid content type'], 400);
            }

            $content = $modelMap[$type]::with('coach:id,first_name,last_name,email')
                ->when($request->coach_id, fn($q, $coachId) => $q->where('coach_id', $coachId))
                ->when($request->search, function($q, $search) {
                    $q->where(function($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                              ->orWhere('title', 'like', "%{$search}%");
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'type' => $type,
                'content' => $content
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch private content', 'error' => $e->getMessage()], 500);
        }
    }
}
