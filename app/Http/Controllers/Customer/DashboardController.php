<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanWorkoutResource;
use App\Models\IntroVideo;
use App\Models\PlanWorkout;
use App\Models\UserCompletedWorkout;
use App\Models\Workout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    //Nutrition Dashboard
    public function getMyNutritionPlan(Request $request)
    {
        $user = $request->user();

        $height = $user->height ?? 0;
        $weight = $user->weight ?? 0;
        $tdee = $user->tdee ?? 0;
        $proteins = $user->protein ?? 0;
        $fats = $user->fat ?? 0;
        $carbs = $user->carb ?? 0;
        $fitness_goal = $user->goal;
        $daily_meal = $user->daily_meal;

        $meals = $this->generateMealChunks($proteins, $fats, $carbs, $daily_meal);

        $reportedContents = Helper::reportedContents($user,"NutritionVideo") ?? [];
        $nutrition_videos = IntroVideo::where('type', 'Nutrition')
        ->when(!empty($reportedContents),function($query) use ($reportedContents){
            $query->whereNotIn('id',$reportedContents);
        })->where('is_active',1)->get();

        $response = [
            "status" => 200,
            "message" => "Nutrition Plan Fetched",
            "height" => $height,
            "weight" => $weight,
            "tdee" => $tdee,
            "proteins" => $proteins,
            "fats" => $fats,
            "carbs" => $carbs,
            "fitness_goal" => $fitness_goal,
            "daily_meal" => $daily_meal,
            "meals" => $meals,
            "nutrition_videos" => $nutrition_videos,
        ];

        return response($response, $response["status"]);
    }

    // Generate meal chunks based on the number of daily meals.
    private function generateMealChunks($proteins, $fats, $carbs, $daily_meal)
    {
        $meals = [];
        if ($daily_meal == 2) {
            $main_meal_protein = $proteins / 2;
            $main_meal_carbs = $carbs / 2;
            $main_meal_fats = $fats / 2;

            $meal_order = ["Breakfast", "Dinner"];

            foreach ($meal_order as $meal_name) {
                $meals[] = [
                    "meal" => $meal_name,
                    "protein" => strval(round($main_meal_protein, 2)),
                    "carb" => strval(round($main_meal_carbs, 2)),
                    "fat" => strval(round($main_meal_fats, 2)),
                ];
            }
        }
        else
        {
            $meals = [];
            $snack_percentage = 0.10;
            $snacks_count = $daily_meal - 3;
            $total_snack_percentage = $snack_percentage * $snacks_count;

            $main_meals_count = 3;
            $main_protein = $proteins * (1 - $total_snack_percentage) / $main_meals_count;
            $main_carbs = $carbs * (1 - $total_snack_percentage) / $main_meals_count;
            $main_fats = $fats * (1 - $total_snack_percentage) / $main_meals_count;

            $snack_protein = $proteins * $snack_percentage;
            $snack_carbs = $carbs * $snack_percentage;
            $snack_fats = $fats * $snack_percentage;

            $meal_order = ["Breakfast", "Lunch", "Dinner"];
            if ($daily_meal >= 4) $meal_order = array_merge(["Breakfast", "Snack"], ["Lunch", "Dinner"]);
            if ($daily_meal >= 5) $meal_order = array_merge(["Breakfast", "Snack"], ["Lunch", "Snack", "Dinner"]);
            if ($daily_meal == 6) $meal_order = array_merge(["Breakfast", "Snack"], ["Lunch", "Snack", "Dinner", "Snack"]);

            foreach ($meal_order as $index => $meal_name) {
                if (strpos($meal_name, "Snack") !== false) {
                    $meals[] = [
                        "meal" => $meal_name,
                        "protein" => strval(round($snack_protein, 2)),
                        "carb" => strval(round($snack_carbs, 2)),
                        "fat" => strval(round($snack_fats, 2)),
                    ];
                } else {
                    $meals[] = [
                        "meal" => $meal_name,
                        "protein" => strval(round($main_protein, 2)),
                        "carb" => strval(round($main_carbs, 2)),
                        "fat" => strval(round($main_fats, 2)),
                    ];
                }
            }
        }
        return $meals;
    }

    //Home Dashboard
    public function getMyDashboardStats(Request $request)
    {
        $currentDate = Carbon::now()->toDateString();

        $user = $request->user();

        $workouts = PlanWorkout::with([
            'plan',
            'workout' => function ($subquery) {
                $subquery->select('id', 'title')
                    ->with('exercise:id,exercise_id,workout_id', 'exercise.video')
                    ->withCount('exercises');
            },
            'user_workout' => function ($subquery) use ($user) {
                $subquery->where('user_id', $user->id)
                    ->select('plan_workout_id', 'start_time', 'end_time', 'status');
            }
        ])
        ->whereHas('plan', function ($query) use ($user) {
            $query->whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })->orWhereHas('organizations', function ($query) use ($user) {
                $query->whereHas('employees', function ($subquery) use ($user) {
                    $subquery->where('id', $user->id);
                });
            })->where('is_active', 1);
        })->where('is_rest',0)->limit('4')->get();

        // if ($workouts->isEmpty()) {
        //     $workouts = PlanWorkout::with([
        //         'plan',
        //         'workout' => function ($subquery) {
        //             $subquery->select('id', 'title')
        //                 ->with('exercise:id,exercise_id,workout_id', 'exercise.video')
        //                 ->withCount('exercises');
        //         },
        //         'user_workout' => function ($subquery) use ($user) {
        //             $subquery->where('user_id', $user->id)
        //                 ->select('plan_workout_id', 'start_time', 'end_time', 'status');
        //         }
        //     ])
        //     ->whereHas('workout')
        //     ->whereHas('plan',function($query) use ($request){
        //         $query->where('type','On Demand');
        //     })
        //     ->where('is_rest', 0)
        //     ->inRandomOrder()
        //     ->limit(4)
        //     ->get()
        //     ->map(function ($workout) {
        //         $workout->is_assigned = 0;
        //         return $workout;
        //     });
        // } else {
        //     $workouts = $workouts->map(function ($workout) {
        //         $workout->is_assigned = 1;
        //         return $workout;
        //     });
        // }
        if (!$workouts->isEmpty())
        {
            $workouts = PlanWorkoutResource::collection($workouts);
        }
        else
        {
            $workouts = null;
        }

        $intro_video = IntroVideo::where('type','General')->where('is_active',1)->first();

        $height = $user->height ?? 0;
        $weight = $user->weight ?? 0;
        $proteins = $user->protein ?? 0;
        $fats = $user->fat ?? 0;
        $carbs = $user->carb ?? 0;

        $completed_workouts = PlanWorkout::whereHas('workout', function ($query) use ($user) {
            $query->whereHas('workoutExercises', function ($subQuery) use ($user) {
                $subQuery->whereHas('userCompletedWorkouts', function ($nestedQuery) use ($user) {
                    $nestedQuery->where('user_id', $user->id)
                                ->where('status', 'Completed');
                });
            })->whereNull('deleted_at');
        })->whereHas('userCompletedWorkouts', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where('status', 'Completed');
        })->get();

        $active_days = UserCompletedWorkout::whereIn('plan_workout_id', $completed_workouts->pluck('id'))
        ->where('user_id', $user->id)
        ->where('status', 'Completed')
        ->select(DB::raw('COUNT(DISTINCT CASE WHEN end_time IS NOT NULL THEN DATE(end_time) END) AS active_days'))
        ->value('active_days');



        $completed_workouts_count = $completed_workouts->count();

        $response = [
            "status" => 200,
            "message" => "Dashboard Stats Fetched",
            "height" => $height,
            "workouts" => $workouts,
            "weight" => $weight,
            "proteins" => $proteins,
            "fats" => $fats,
            "carbs" => $carbs,
            "intro_video" => $intro_video,
            "completed_workouts_count" => $completed_workouts_count,
            "active_days" => $active_days
        ];

        return response($response, $response["status"]);
    }

    /**
     * Get leaderboard data - top users by body points for the organization
     */
    public function getLeaderboard(Request $request)
    {
        $user = $request->user();
        $organizationId = $request->input('organization_id', $user->organization_id);
        $limit = $request->input('limit', 10);

        try {
            // Get top users by body points from the same organization
            $leaderboardUsers = DB::table('users')
                ->select('id', 'first_name', 'last_name', 'body_points', 'profile_image_thumbnail', 'department')
                ->where('organization_id', $organizationId)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->orderBy('body_points', 'desc')
                ->limit($limit)
                ->get();

            $response = [
                "status" => 200,
                "success" => true,
                "message" => "Leaderboard fetched successfully",
                "data" => $leaderboardUsers
            ];

            return response($response, $response["status"]);
        } catch (\Exception $e) {
            $response = [
                "status" => 500,
                "success" => false,
                "message" => "Failed to fetch leaderboard: " . $e->getMessage(),
                "data" => []
            ];

            return response($response, $response["status"]);
        }
    }

    /**
     * Get current user's rank in the leaderboard
     */
    public function getUserRank(Request $request)
    {
        $user = $request->user();
        $organizationId = $request->input('organization_id', $user->organization_id);
        $userId = $request->input('user_id', $user->id);

        try {
            // Get user's rank by counting users with higher body points
            $rank = DB::table('users')
                ->where('organization_id', $organizationId)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->where('body_points', '>', $user->body_points)
                ->count() + 1;

            $response = [
                "status" => 200,
                "success" => true,
                "message" => "User rank fetched successfully",
                "rank" => $rank,
                "user_body_points" => $user->body_points
            ];

            return response($response, $response["status"]);
        } catch (\Exception $e) {
            $response = [
                "status" => 500,
                "success" => false,
                "message" => "Failed to fetch user rank: " . $e->getMessage(),
                "rank" => 0
            ];

            return response($response, $response["status"]);
        }
    }
}
