<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

class LibraryController extends Controller
{
    /**
     * Browse workout library (public + coach's private)
     */
    public function browseWorkouts(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Get public library workouts
            $publicWorkouts = WorkoutLibrary::query()
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->when($request->difficulty, fn($q, $difficulty) => $q->difficulty($difficulty))
                ->when($request->featured, fn($q) => $q->featured())
                ->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($workout) {
                    $workout->source = 'public';
                    return $workout;
                });

            // Get coach's private workouts (using existing workout_plans table)
            $privateWorkouts = WorkoutPlan::where('coach_id', $coach->id)
                ->when($request->category, fn($q, $category) => $q->where('category', $category))
                ->when($request->difficulty, fn($q, $difficulty) => $q->where('difficulty_level', $difficulty))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($workout) {
                    $workout->source = 'private';
                    return $workout;
                });

            // Merge and return
            $combined = $publicWorkouts->concat($privateWorkouts)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'workouts' => $combined,
                'public_count' => $publicWorkouts->count(),
                'private_count' => $privateWorkouts->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to browse workouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse nutrition plan library (public + coach's private)
     */
    public function browseNutritionPlans(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Get public library plans
            $publicPlans = NutritionPlanLibrary::query()
                ->when($request->goal_type, fn($q, $goalType) => $q->goalType($goalType))
                ->when($request->featured, fn($q) => $q->featured())
                ->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($plan) {
                    $plan->source = 'public';
                    return $plan;
                });

            // Get coach's private plans (using existing nutrition_plans table)
            $privatePlans = NutritionPlan::where('coach_id', $coach->id)
                ->when($request->goal_type, fn($q, $goalType) => $q->where('goal_type', $goalType))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($plan) {
                    $plan->source = 'private';
                    return $plan;
                });

            // Merge and return
            $combined = $publicPlans->concat($privatePlans)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'plans' => $combined,
                'public_count' => $publicPlans->count(),
                'private_count' => $privatePlans->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to browse nutrition plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse challenge library (public + coach's private)
     */
    public function browseChallenges(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Get public library challenges
            $publicChallenges = ChallengeLibrary::query()
                ->when($request->challenge_type, fn($q, $type) => $q->challengeType($type))
                ->when($request->featured, fn($q) => $q->featured())
                ->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($challenge) {
                    $challenge->source = 'public';
                    return $challenge;
                });

            // Get coach's private challenges
            $privateChallenges = CoachChallenge::where('coach_id', $coach->id)
                ->when($request->challenge_type, fn($q, $type) => $q->challengeType($type))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($challenge) {
                    $challenge->source = 'private';
                    return $challenge;
                });

            // Merge and return
            $combined = $publicChallenges->concat($privateChallenges)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'challenges' => $combined,
                'public_count' => $publicChallenges->count(),
                'private_count' => $privateChallenges->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to browse challenges',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse fitness videos library (public + coach's private)
     */
    public function browseFitnessVideos(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Get public library videos
            $publicVideos = FitnessVideosLibrary::query()
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->when($request->difficulty, fn($q, $difficulty) => $q->difficulty($difficulty))
                ->when($request->featured, fn($q) => $q->featured())
                ->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($video) {
                    $video->source = 'public';
                    return $video;
                });

            // Get coach's private videos
            $privateVideos = CoachFitnessVideo::where('coach_id', $coach->id)
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->when($request->difficulty, fn($q, $difficulty) => $q->difficulty($difficulty))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($video) {
                    $video->source = 'private';
                    return $video;
                });

            // Merge and return
            $combined = $publicVideos->concat($privateVideos)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'videos' => $combined,
                'public_count' => $publicVideos->count(),
                'private_count' => $privateVideos->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to browse fitness videos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse nutrition videos library (public + coach's private)
     */
    public function browseNutritionVideos(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Get public library videos
            $publicVideos = NutritionVideosLibrary::query()
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->when($request->featured, fn($q) => $q->featured())
                ->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($video) {
                    $video->source = 'public';
                    return $video;
                });

            // Get coach's private videos
            $privateVideos = CoachNutritionVideo::where('coach_id', $coach->id)
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($video) {
                    $video->source = 'private';
                    return $video;
                });

            // Merge and return
            $combined = $publicVideos->concat($privateVideos)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'videos' => $combined,
                'public_count' => $publicVideos->count(),
                'private_count' => $privateVideos->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to browse nutrition videos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse mindset videos library (public + coach's private)
     */
    public function browseMindsetVideos(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Get public library videos
            $publicVideos = MindsetVideosLibrary::query()
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->when($request->featured, fn($q) => $q->featured())
                ->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($video) {
                    $video->source = 'public';
                    return $video;
                });

            // Get coach's private videos
            $privateVideos = CoachMindsetVideo::where('coach_id', $coach->id)
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($video) {
                    $video->source = 'private';
                    return $video;
                });

            // Merge and return
            $combined = $publicVideos->concat($privateVideos)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'videos' => $combined,
                'public_count' => $publicVideos->count(),
                'private_count' => $privateVideos->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to browse mindset videos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Browse notification videos library (public + coach's private)
     */
    public function browseNotificationVideos(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Get public library videos
            $publicVideos = NotificationVideosLibrary::query()
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->when($request->featured, fn($q) => $q->featured())
                ->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($video) {
                    $video->source = 'public';
                    return $video;
                });

            // Get coach's private videos
            $privateVideos = CoachNotificationVideo::where('coach_id', $coach->id)
                ->when($request->category, fn($q, $category) => $q->category($category))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($video) {
                    $video->source = 'private';
                    return $video;
                });

            // Merge and return
            $combined = $publicVideos->concat($privateVideos)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'videos' => $combined,
                'public_count' => $publicVideos->count(),
                'private_count' => $privateVideos->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to browse notification videos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clone from public library to coach's private collection
     */
    public function cloneFromLibrary(Request $request)
    {
        try {
            $coach = Auth::guard('admin')->user() ?? Auth::user();
            if (!$coach) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'library_type' => 'required|in:workout,nutrition_plan,challenge,fitness_video,nutrition_video,mindset_video,notification_video',
                'library_id' => 'required|integer'
            ]);

            $cloned = null;

            switch ($validated['library_type']) {
                case 'workout':
                    $source = WorkoutLibrary::findOrFail($validated['library_id']);
                    $cloned = WorkoutPlan::create([
                        'coach_id' => $coach->id,
                        'name' => $source->name . ' (Copy)',
                        'description' => $source->description,
                        'category' => $source->category,
                        'difficulty_level' => $source->difficulty_level,
                        'goal' => $source->goal,
                        'duration_weeks' => $source->duration_weeks,
                        'sessions_per_week' => $source->sessions_per_week,
                        'exercises' => $source->exercises,
                        'thumbnail_url' => $source->thumbnail_url,
                        'notes' => $source->notes
                    ]);
                    $source->incrementCloneCount();
                    break;

                case 'nutrition_plan':
                    $source = NutritionPlanLibrary::findOrFail($validated['library_id']);
                    $cloned = NutritionPlan::create([
                        'coach_id' => $coach->id,
                        'name' => $source->name . ' (Copy)',
                        'description' => $source->description,
                        'duration_days' => $source->duration_days,
                        'daily_calories' => $source->daily_calories,
                        'daily_protein_g' => $source->daily_protein_g,
                        'daily_carbs_g' => $source->daily_carbs_g,
                        'daily_fat_g' => $source->daily_fat_g,
                        'goal_type' => $source->goal_type,
                        'activity_level' => $source->activity_level,
                        'meals' => $source->meals,
                        'thumbnail_url' => $source->thumbnail_url,
                        'notes' => $source->notes
                    ]);
                    $source->incrementCloneCount();
                    break;

                case 'challenge':
                    $source = ChallengeLibrary::findOrFail($validated['library_id']);
                    $cloned = CoachChallenge::create([
                        'coach_id' => $coach->id,
                        'name' => $source->name . ' (Copy)',
                        'description' => $source->description,
                        'challenge_type' => $source->challenge_type,
                        'duration_days' => $source->duration_days,
                        'daily_tasks' => $source->daily_tasks,
                        'rules' => $source->rules,
                        'rewards' => $source->rewards,
                        'thumbnail_url' => $source->thumbnail_url,
                        'notes' => $source->notes,
                        'cloned_from_library_id' => $source->id
                    ]);
                    $source->incrementCloneCount();
                    break;

                case 'fitness_video':
                    $source = FitnessVideosLibrary::findOrFail($validated['library_id']);
                    $cloned = CoachFitnessVideo::create([
                        'coach_id' => $coach->id,
                        'title' => $source->title . ' (Copy)',
                        'description' => $source->description,
                        'video_url' => $source->video_url,
                        'thumbnail_url' => $source->thumbnail_url,
                        'duration_seconds' => $source->duration_seconds,
                        'category' => $source->category,
                        'difficulty_level' => $source->difficulty_level,
                        'tags' => $source->tags,
                        'notes' => $source->notes,
                        'cloned_from_library_id' => $source->id
                    ]);
                    $source->incrementCloneCount();
                    break;

                case 'nutrition_video':
                    $source = NutritionVideosLibrary::findOrFail($validated['library_id']);
                    $cloned = CoachNutritionVideo::create([
                        'coach_id' => $coach->id,
                        'title' => $source->title . ' (Copy)',
                        'description' => $source->description,
                        'video_url' => $source->video_url,
                        'thumbnail_url' => $source->thumbnail_url,
                        'duration_seconds' => $source->duration_seconds,
                        'category' => $source->category,
                        'tags' => $source->tags,
                        'notes' => $source->notes,
                        'cloned_from_library_id' => $source->id
                    ]);
                    $source->incrementCloneCount();
                    break;

                case 'mindset_video':
                    $source = MindsetVideosLibrary::findOrFail($validated['library_id']);
                    $cloned = CoachMindsetVideo::create([
                        'coach_id' => $coach->id,
                        'title' => $source->title . ' (Copy)',
                        'description' => $source->description,
                        'video_url' => $source->video_url,
                        'thumbnail_url' => $source->thumbnail_url,
                        'duration_seconds' => $source->duration_seconds,
                        'category' => $source->category,
                        'tags' => $source->tags,
                        'notes' => $source->notes,
                        'cloned_from_library_id' => $source->id
                    ]);
                    $source->incrementCloneCount();
                    break;

                case 'notification_video':
                    $source = NotificationVideosLibrary::findOrFail($validated['library_id']);
                    $cloned = CoachNotificationVideo::create([
                        'coach_id' => $coach->id,
                        'title' => $source->title . ' (Copy)',
                        'description' => $source->description,
                        'video_url' => $source->video_url,
                        'thumbnail_url' => $source->thumbnail_url,
                        'duration_seconds' => $source->duration_seconds,
                        'category' => $source->category,
                        'tags' => $source->tags,
                        'notes' => $source->notes,
                        'cloned_from_library_id' => $source->id
                    ]);
                    $source->incrementCloneCount();
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully cloned to your private collection',
                'cloned_item' => $cloned
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clone item',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
