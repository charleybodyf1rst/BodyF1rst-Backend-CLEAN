<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AssignPlan;
use App\Models\Plan;
use App\Models\PlanWorkout;
use App\Models\Workout;
use App\Models\Exercise;
use App\Models\ExerciseVideo;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserCompletedWorkout;
use App\Models\Video;
use App\Models\WorkoutExercise;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function addPlan(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'type' => 'required|in:On Demand,Program',
            'phase' => 'required_if:type,Program',
            'week' => 'required_if:type,Program',
            'workouts' => 'required|array',
            'workouts.*.phase' => 'numeric|nullable',
            'workouts.*.week' => 'numeric|nullable',
            'workouts.*.day' => 'numeric|nullable',
            'sort' => 'numeric|nullable',
        ]);



        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }
        DB::beginTransaction();
        $plan = Plan::create($request->toArray());
        $plan->uploader = $role;
        $plan->uploaded_by = $userId;
        if (isset($request->workouts)) {

            $values = [];
            foreach ($request->workouts as $key => $workout) {
                $workoutId = $workout['workout_id'] ?? null;





















                $values[] = [
                    "plan_id" => $plan->id,
                    "phase" => $workout['phase'] ?? null,
                    "week" => $workout['week'] ?? null,
                    "day" => $workout['day'] ?? null,
                    "workout_id" => $workout['is_rest'] == 1 ? null : $workoutId,
                    "is_rest" => $workout['is_rest'] ?? 0,
                    "sort" => $workout['sort'] ?? $key + 1,
                    "created_at" => Carbon::now(),
                    "updated_at" => Carbon::now()
                ];
            }
            PlanWorkout::insert($values);
        }
        $plan->is_active = 1;
        $plan->visibility_type = $request->visibility_type ?? "Public";
        $plan->save();

        $plan->load('upload_by:id,first_name,last_name,profile_image');
        $weeksByPhase = $plan->totalWeeks();
        $plan['total_weeks'] = $weeksByPhase;
        Helper::createActionLog($userId,$role,'plans','add',null,$plan);
        DB::commit();
        $response = [
            "status" => 200,
            "message" => "Plan Added Successfully",
            "plan" => $plan,
        ];
        return response($response, $response["status"]);
    }
    public function addPlanWithCloning(Request $request)
    {
        ini_set('max_execution_time', 0);
        DB::beginTransaction();

        DB::enableQueryLog();

            $role = $request->role;
            $userId = Auth::guard(strtolower($role))->id();
            $validator = Validator::make($request->all(), [
                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'type' => 'required|in:On Demand,Program',
                'phase' => 'required_if:type,Program',
                'week' => 'required_if:type,Program',
                'workouts' => 'required|array',
                'workouts.*.phase' => 'numeric|nullable',
                'workouts.*.week' => 'numeric|nullable',
                'workouts.*.day' => 'numeric|nullable',
                'sort' => 'numeric|nullable',
            ]);



            if ($validator->fails()) {
                $response = [
                    "status" => 422,
                    "message" => $validator->errors()->first(),
                    "errors" => $validator->errors(),
                ];
                return response($response, $response["status"]);
            }
            $userWorkouts = Workout::where('uploaded_by', $userId)
                ->where('uploader', $role)
                ->with('exercises.exercise')
                ->get()
                ->keyBy('id');

            $allWorkouts = Workout::with('exercises.exercise')->get()
                ->keyBy('id');


            $userExercises = Exercise::where('uploaded_by', $userId)
                ->where('uploader', $role)
                ->with('video')
                ->get()
                ->keyBy('id');
            $allExercises = Exercise::with('video')->get()
                ->keyBy('id');

            $userVideos = Video::where('uploaded_by', $userId)
                ->where('uploader', $role)
                ->get()
                ->keyBy('id');

            $allVideos = Video::get()->keyBy('id');
            $clonedWorkouts = [];
            $clonedExercises = [];
            $clonedVideos = [];

            $cloneEntity = function ($entity, $userId, $role, &$clonedMap, $type) {
                if ($type == "video") {







                    unset($entity->laravel_through_key);
                    $key = "video_title";
                    $title = $entity->video_title ;
                }
                else
                {
                    $key = "title";
                    $title = $entity->title ;
                }

                if (isset($clonedMap[$entity->id])) {
                    return $clonedMap[$entity->id];
                }

                $cloned = $entity->replicate()->fill([
                    "$key" => $title,
                ]);

                $cloned->uploaded_by = $userId;
                $cloned->uploader = $role;
                $cloned->parent_id = $entity->id;
                $cloned->save();

                $clonedMap[$entity->id] = $cloned->id;

                return $cloned->id;
            };


            $plan = Plan::create($request->toArray());
            $plan->uploader = $role;
            $plan->uploaded_by = $userId;

            $planWorkouts = [];
            $workoutExercisePivot = [];
            $exerciseVideoPivot = [];
            $checkedWorkout = [];
            $checkedExercise = [];
            $checkedVideo = [];

            foreach ($request->workouts as $key=>$workoutData) {
                $workout_Id = $workoutData['workout_id'] ?? null;
                if($workout_Id)
                {
                    $workout = $userWorkouts->get($workoutData['workout_id']);
                    if (!$workout) {
                        $workout = $userWorkouts->firstWhere('parent_id', $workoutData['workout_id']);

                        if (!$workout) {
                            $workout = $allWorkouts->get($workoutData['workout_id']);

                            if (!$workout) {
                                $workout = Workout::with('exercises.exercise')->find($workoutData['workout_id']);
                                $workoutId = $cloneEntity($workout, $userId, $role, $clonedWorkouts, 'workout');
                            } else {
                                $workoutId = $cloneEntity($workout, $userId, $role, $clonedWorkouts, 'workout');
                            }
                        } else {
                            $workoutId = $workout->id;
                        }
                    } else {
                        $workoutId = $workout->id;
                    }
                }


                $planWorkouts[] = [
                    "plan_id" => $plan->id,
                    "phase" => $workoutData['phase'] ?? null,
                    "week" => $workoutData['week'] ?? null,
                    "day" => $workoutData['day'] ?? null,
                    "workout_id" => $workoutData['is_rest'] == 1 ? null : $workoutId,
                    "is_rest" => $workoutData['is_rest'] ?? 0,
                    "sort" => $workoutData['sort'] ?? $key + 1,
                    "created_at" => Carbon::now(),
                    "updated_at" => Carbon::now()
                ];
                if(isset($workoutData['workout_id']) && !$userWorkouts->get($workoutData['workout_id']) && !$userWorkouts->firstWhere('parent_id', $workoutData['workout_id']))
                {
                    if(!in_array($workoutId,$checkedWorkout))
                    {
                        $exercises = $workout->exercises;

                        foreach ($exercises as $exerciseData) {

                                if(isset($exerciseData['is_rest']) && $exerciseData['is_rest'] == 1)
                                {
                                    $workoutExercisePivot[] = [
                                        'workout_id' => $workoutId,
                                        'exercise_id' => null,
                                        'is_rest' => 1,
                                        "type" => $exerciseData['type'] ?? null,
                                        "min" => $exerciseData['min'] ?? null,
                                        "sec" => $exerciseData['sec'] ?? null,
                                        "set" => $exerciseData['set'] ?? null,
                                        "rep" => $exerciseData['rep'] ?? null,
                                        "rest_min" => $exerciseData['rest_min'] ?? null,
                                        "rest_sec" => $exerciseData['rest_sec'] ?? null,
                                        "is_stag" => $exerciseData['is_stag'] ?? null,
                                        "stag" => $exerciseData['stag'] ?? null,
                                        "stagger" => isset($exerciseData['stagger']) ? json_encode($exerciseData['stagger']) : null,
                                        'superset'=> $exerciseData['superset'] ?? null,
                                        "sort" => $exerciseData['sort'] ?? null,
                                        "created_at" => Carbon::now(),
                                        "updated_at" => Carbon::now()
                                    ];
                                }
                                if (isset($exerciseData['exercise']['id'])) {
                                    if(!in_array($exerciseData['exercise']['id'],$checkedExercise))
                                    {
                                        $exercise = $userExercises->get($exerciseData['exercise']['id']);

                                        if (!$exercise) {
                                            $exercise = $userExercises->firstWhere('parent_id', $exerciseData['exercise']['id']);
                                            if(!$exercise)
                                            {
                                                $exercise = $allExercises->get($exerciseData['exercise']['id']);

                                                if (!$exercise) {
                                                    $exercise = Exercise::with('video')->find($exerciseData['exercise']['id']);
                                                    $exerciseId = $cloneEntity($exercise, $userId, $role, $clonedExercises, 'exercise');
                                                } else {
                                                    $exerciseId = $cloneEntity($exercise, $userId, $role, $clonedExercises, 'exercise');
                                                }
                                            }
                                            else
                                            {
                                                $exerciseId = $exercise->id;
                                            }
                                        } else {
                                            $exerciseId = $exercise->id;
                                        }

                                            $workoutExercisePivot[] = [
                                                'workout_id' => $workoutId,
                                                'exercise_id' => $exerciseData['is_rest'] == 1 ? null : $exerciseId,
                                                'is_rest' => $exerciseData['is_rest'] ?? 0,
                                                "type" => $exerciseData['type'] ?? null,
                                                "min" => $exerciseData['min'] ?? null,
                                                "sec" => $exerciseData['sec'] ?? null,
                                                "set" => $exerciseData['set'] ?? null,
                                                "rep" => $exerciseData['rep'] ?? null,
                                                "rest_min" => $exerciseData['rest_min'] ?? null,
                                                "rest_sec" => $exerciseData['rest_sec'] ?? null,
                                                "is_stag" => $exerciseData['is_stag'] ?? null,
                                                "stag" => $exerciseData['stag'] ?? null,
                                                "stagger" => isset($exerciseData['stagger']) ? json_encode($exerciseData['stagger']) : null,
                                                'superset'=> $exerciseData['superset'] ?? null,
                                                "sort" => $exerciseData['sort'] ?? null,
                                                "created_at" => Carbon::now(),
                                                "updated_at" => Carbon::now()
                                            ];

                                        if (isset($exercise['video'])) {
                                        if(!in_array($exercise['video']['id'],$checkedVideo))
                                        {
                                                $videoData = $exercise['video'];

                                                if (isset($videoData['id'])) {
                                                    $video = $userVideos->get($videoData['id']);

                                                    if (!$video) {
                                                        $video = $userVideos->firstWhere('parent_id', $videoData['id']);
                                                        if(!$video)
                                                        {
                                                            $video = $allVideos->get($videoData['id']);
                                                            if (!$video) {
                                                                $video = Video::find($videoData['id']);
                                                                $videoId = $cloneEntity($video, $userId, $role, $clonedVideos, 'video');
                                                            } else {
                                                                $videoId = $cloneEntity($video, $userId, $role, $clonedVideos, 'video');
                                                            }
                                                        }
                                                        else {
                                                            $videoId = $video->id;
                                                        }
                                                    } else {
                                                        $videoId = $video->id;
                                                    }
                                                    $checkedVideo[] = $videoId;
                                                        $exerciseVideoPivot[] = [
                                                            'exercise_id' => $exerciseId,
                                                            'video_id' => $videoId,
                                                        ];
                                                }
                                            }
                                        }

                                        $checkedExercise[] = $exerciseId;
                                    }
                                }

                        }
                    }
                }
                $checkedWorkout[] = $workoutId;
            }

            if (!empty($planWorkouts)) {
                PlanWorkout::insert($planWorkouts);
            }

            if (!empty($workoutExercisePivot)) {
                WorkoutExercise::insert($workoutExercisePivot);
            }

            if (!empty($exerciseVideoPivot)) {
                ExerciseVideo::insert($exerciseVideoPivot);
            }

            $queries = DB::getQueryLog();
            $plan->is_active = 1;
            $plan->visibility_type = $request->visibility_type ?? "Public";
            $plan->save();

            $plan->load('upload_by:id,first_name,last_name,profile_image');
            $weeksByPhase = $plan->totalWeeks();
            $plan['total_weeks'] = $weeksByPhase;
            Helper::createActionLog($userId,$role,'plans','add',null,$plan);
            DB::commit();

            $response = [
                'status' => 200,
                'message' => 'Plan created successfully!',
                'plan' => $plan,
                'queries' => $queries,
                "planWorkouts" => $planWorkouts,
                "workoutExercisePivot" => $workoutExercisePivot,
                "exerciseVideoPivot" => $exerciseVideoPivot,
            ];
            return response($response,$response["status"]);

    }
    public function updatePlanWithCloning(Request $request,$id)
    {
        ini_set('max_execution_time', 0);
        DB::beginTransaction();

        DB::enableQueryLog();


        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'title' => [
                'string',
                'max:255',
            ],
            'type' => 'in:On Demand,Program',
            'workouts' => 'array',
            'sort' => 'numeric|nullable',
            'deleted_ids' => 'array'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

            $userWorkouts = Workout::where('uploaded_by', $userId)
                ->where('uploader', $role)
                ->with('exercises.exercise')
                ->get()
                ->keyBy('id');

            $allWorkouts = Workout::with('exercises.exercise')->get()
                ->keyBy('id');

            $userExercises = Exercise::where('uploaded_by', $userId)
                ->where('uploader', $role)
                ->with('video')
                ->get()
                ->keyBy('id');
            $allExercises = Exercise::with('video')->get()
                ->keyBy('id');

            $userVideos = Video::where('uploaded_by', $userId)
                ->where('uploader', $role)
                ->get()
                ->keyBy('id');

            $allVideos = Video::get()->keyBy('id');

            $clonedWorkouts = [];
            $clonedExercises = [];
            $clonedVideos = [];

            $cloneEntity = function ($entity, $userId, $role, &$clonedMap, $type) {
                if ($type == "video") {







                    unset($entity->laravel_through_key);
                    $key = "video_title";
                    $title = $entity->video_title ;
                }
                else
                {
                    $key = "title";
                    $title = $entity->title ;
                }

                if (isset($clonedMap[$entity->id])) {
                    return $clonedMap[$entity->id];
                }

                $cloned = $entity->replicate()->fill([
                    "$key" => $title,
                ]);

                $cloned->uploaded_by = $userId;
                $cloned->uploader = $role;
                $cloned->parent_id = $entity->id;



                $cloned->save();

                $clonedMap[$entity->id] = $cloned->id;

                return $cloned->id;
            };

            $plan = Plan::find($id);
            if(isset($plan))
            {
                if(isset($request->deleted_ids))
                {
                    UserCompletedWorkout::whereIn('plan_workout_id',$request->deleted_ids)->delete();
                    PlanWorkout::whereIn('id',$request->deleted_ids)->delete();
                }
                $plan->fill($request->toArray());
                $plan->uploader = $role;
                $plan->uploaded_by = $userId;

                $planWorkouts = [];
                $workoutExercisePivot = [];
                $exerciseVideoPivot = [];
                $checkedWorkout = [];
                $checkedExercise = [];
                $checkedVideo = [];

                $onlyIsActive = $request->only(['is_active']) == $request->all();

                $message = '';
                if ($request->filled('is_active')) {
                    $plan->is_active = $request->is_active;
                    $message = $request->is_active == 1 ? 'Plan Active Successfully' : 'Plan Blocked Successfully';
                }
                $plan->uploader = $role;
                $plan->uploaded_by = $userId;
                if (isset($request->workouts)) {
                    foreach ($request->workouts as $key=>$workoutData) {

                        $workoutId = $workoutData['workout_id'] ?? null;
                        if($workoutId)
                        {
                            $workout = $userWorkouts->get($workoutData['workout_id']);

                            if (!$workout) {
                                $workout = $userWorkouts->firstWhere('parent_id', $workoutData['workout_id']);

                                if (!$workout) {
                                    $workout = $allWorkouts->get($workoutData['workout_id']);

                                    if (!$workout) {
                                        $workout = Workout::with('exercises.exercise')->find($workoutData['workout_id']);
                                        $workoutId = $cloneEntity($workout, $userId, $role, $clonedWorkouts, 'workout');
                                    } else {
                                        $workoutId = $cloneEntity($workout, $userId, $role, $clonedWorkouts, 'workout');
                                    }
                                } else {
                                    $workoutId = $workout->id;
                                }
                            } else {
                                $workoutId = $workout->id;
                            }
                        }

                        $planWorkouts[] = [
                            "id" => $workoutData['plan_workout_id'] ?? null,
                            "plan_id" => $plan->id,
                            "phase" => $workoutData['phase'] ?? null,
                            "week" => $workoutData['week'] ?? null,
                            "day" => $workoutData['day'] ?? null,
                            "workout_id" => $workoutData['is_rest'] == 1 ? null : $workoutId,
                            "is_rest" => $workoutId ? 0 : 1,
                            "sort" => $workoutData['sort'] ?? $key + 1,
                            "created_at" => Carbon::now(),
                            "updated_at" => Carbon::now()
                        ];

                        if(isset($workoutData['workout_id']) && !$userWorkouts->get($workoutData['workout_id']) && !$userWorkouts->firstWhere('parent_id', $workoutData['workout_id']))
                        {
                            if(!in_array($workoutId,$checkedWorkout))
                            {
                                $exercises = $workout->exercises;

                                foreach ($exercises as $exerciseData) {

                                        if(isset($exerciseData['is_rest']) && $exerciseData['is_rest'] == 1)
                                        {
                                            $workoutExercisePivot[] = [
                                                'workout_id' => $workoutId,
                                                'exercise_id' => null,
                                                'is_rest' => 1,
                                                "type" => $exerciseData['type'] ?? null,
                                                "min" => $exerciseData['min'] ?? null,
                                                "sec" => $exerciseData['sec'] ?? null,
                                                "set" => $exerciseData['set'] ?? null,
                                                "rep" => $exerciseData['rep'] ?? null,
                                                "rest_min" => $exerciseData['rest_min'] ?? null,
                                                "rest_sec" => $exerciseData['rest_sec'] ?? null,
                                                "is_stag" => $exerciseData['is_stag'] ?? null,
                                                "stag" => $exerciseData['stag'] ?? null,
                                                "stagger" => isset($exerciseData['stagger']) ? json_encode($exerciseData['stagger']) : null,
                                                'superset'=> $exerciseData['superset'] ?? null,
                                                "sort" => $exerciseData['sort'] ?? null,
                                                "created_at" => Carbon::now(),
                                                "updated_at" => Carbon::now()
                                            ];
                                        }
                                        if (isset($exerciseData['exercise']['id'])) {
                                            if(!in_array($exerciseData['exercise']['id'],$checkedExercise))
                                            {
                                                $exercise = $userExercises->get($exerciseData['exercise']['id']);

                                                if (!$exercise) {
                                                    $exercise = $userExercises->firstWhere('parent_id', $exerciseData['exercise']['id']);
                                                    if(!$exercise)
                                                    {
                                                        $exercise = $allExercises->get($exerciseData['exercise']['id']);

                                                        if (!$exercise) {
                                                            $exercise = Exercise::with('video')->find($exerciseData['exercise']['id']);
                                                            $exerciseId = $cloneEntity($exercise, $userId, $role, $clonedExercises, 'exercise');
                                                        } else {
                                                            $exerciseId = $cloneEntity($exercise, $userId, $role, $clonedExercises, 'exercise');
                                                        }
                                                    }
                                                    else
                                                    {
                                                        $exerciseId = $exercise->id;
                                                    }
                                                } else {
                                                    $exerciseId = $exercise->id;
                                                }

                                                    $workoutExercisePivot[] = [
                                                        'workout_id' => $workoutId,
                                                        'exercise_id' => $exerciseData['is_rest'] == 1 ? null : $exerciseId,
                                                        'is_rest' => $exerciseData['is_rest'] ?? 0,
                                                        "type" => $exerciseData['type'] ?? null,
                                                        "min" => $exerciseData['min'] ?? null,
                                                        "sec" => $exerciseData['sec'] ?? null,
                                                        "set" => $exerciseData['set'] ?? null,
                                                        "rep" => $exerciseData['rep'] ?? null,
                                                        "rest_min" => $exerciseData['rest_min'] ?? null,
                                                        "rest_sec" => $exerciseData['rest_sec'] ?? null,
                                                        "is_stag" => $exerciseData['is_stag'] ?? null,
                                                        "stag" => $exerciseData['stag'] ?? null,
                                                        "stagger" => isset($exerciseData['stagger']) ? json_encode($exerciseData['stagger']) : null,
                                                        'superset'=> $exerciseData['superset'] ?? null,
                                                        "sort" => $exerciseData['sort'] ?? null,
                                                        "created_at" => Carbon::now(),
                                                        "updated_at" => Carbon::now()
                                                    ];

                                                if (isset($exercise['video'])) {
                                                if(!in_array($exercise['video']['id'],$checkedVideo))
                                                {
                                                        $videoData = $exercise['video'];

                                                        if (isset($videoData['id'])) {
                                                            $video = $userVideos->get($videoData['id']);

                                                            if (!$video) {
                                                                $video = $userVideos->firstWhere('parent_id', $videoData['id']);
                                                                if(!$video)
                                                                {
                                                                    $video = $allVideos->get($videoData['id']);
                                                                    if (!$video) {
                                                                        $video = Video::find($videoData['id']);
                                                                        $videoId = $cloneEntity($video, $userId, $role, $clonedVideos, 'video');
                                                                    } else {
                                                                        $videoId = $cloneEntity($video, $userId, $role, $clonedVideos, 'video');
                                                                    }
                                                                }
                                                                else {
                                                                    $videoId = $video->id;
                                                                }
                                                            } else {
                                                                $videoId = $video->id;
                                                            }
                                                            $checkedVideo[] = $videoId;
                                                                $exerciseVideoPivot[] = [
                                                                    'exercise_id' => $exerciseId,
                                                                    'video_id' => $videoId,
                                                                ];
                                                        }
                                                    }
                                                }

                                                $checkedExercise[] = $exerciseId;
                                            }
                                        }

                                }
                            }
                        }
                        $checkedWorkout[] = $workoutId;
                    }
                }

                if (!empty($planWorkouts)) {
                    PlanWorkout::upsert($planWorkouts,['id'],['plan_id','phase','week','day','workout_id','is_rest','sort','updated_at']);
                }

                if (!empty($workoutExercisePivot)) {
                    WorkoutExercise::insert($workoutExercisePivot);
                }

                if (!empty($exerciseVideoPivot)) {
                    ExerciseVideo::insert($exerciseVideoPivot);
                }
                $plan->visibility_type = $request->visibility_type ?? "Public";
                $plan->save();

                $plan->load('upload_by:id,first_name,last_name,profile_image');
                $weeksByPhase = $plan->totalWeeks();
                $plan['total_weeks'] = $weeksByPhase;
                DB::commit();

                $queries = DB::getQueryLog();
                if ($onlyIsActive) {
                    $response = [
                        "status" => 200,
                        "message" => $message,
                        "plan" => $plan
                    ];
                    return response($response, $response["status"]);
                } else {
                    $response = [
                        "status" => 200,
                        "message" => "Plan Updated Successfully",
                        "plan" => $plan
                    ];
                }

            }
            else
            {
                $response = [
                    "status" => 422,
                    "message" => "Plan Not Found"
                ];

            }
            return response($response,$response['status']);
    }
    public function updatePlan(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'title' => [
                'string',
                'max:255',
            ],
            'type' => 'in:On Demand,Program',
            'workouts' => 'array',
            'sort' => 'numeric|nullable',
            'deleted_ids' => 'array'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }
        DB::beginTransaction();
        $plan = Plan::find($id);
        if (isset($plan)) {
            if(isset($request->deleted_ids))
            {
                UserCompletedWorkout::whereIn('plan_workout_id',$request->deleted_ids)->delete();
                PlanWorkout::whereIn('id',$request->deleted_ids)->delete();
            }
            $plan->load('upload_by:id,first_name,last_name,profile_image');
            $weeksByPhase = $plan->totalWeeks();
            $plan['total_weeks'] = $weeksByPhase;
            $before_data = $plan->replicate();
            unset($plan['total_weeks']);
            $plan->fill($request->except(['total_weeks']));
            $onlyIsActive = $request->only(['is_active']) == $request->all();
            $message = '';
            if ($request->filled('is_active')) {
                $plan->is_active = $request->is_active;
                $message = $request->is_active == 1 ? 'Plan Active Successfully' : 'Plan Blocked Successfully';
            }
            $plan->uploader = $role;
            $plan->uploaded_by = $userId;
            if (isset($request->workouts)) {
                $values = [];
                foreach ($request->workouts as $key => $workout) {
                    $workoutId = $workout['workout_id'] ?? null;





















                    $values[] = [
                        "plan_id" => $plan->id,
                        "id" => $workout['plan_workout_id'] ?? null,
                        "phase" => $workout['phase'] ?? null,
                        "week" => $workout['week'] ?? null,
                        "day" => $workout['day'] ?? null,
                        "workout_id" => $workout['is_rest'] == 1 ? null : $workoutId,
                        "is_rest" => $workout['is_rest'] ?? 0,
                        "sort" => $workout['sort'] ?? $key + 1,
                        "created_at" => Carbon::now(),
                        "updated_at" => Carbon::now()
                    ];
                }
                PlanWorkout::upsert($values,['id'],['plan_id','phase','week','day','workout_id','is_rest','sort','updated_at']);
            }
            $plan->visibility_type = $request->visibility_type ?? "Public";
            $plan->save();

            $plan->load('upload_by:id,first_name,last_name,profile_image');
            $weeksByPhase = $plan->totalWeeks();
            $plan['total_weeks'] = $weeksByPhase;

            if ($onlyIsActive) {
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "plan" => $plan
                ];
                return response($response, $response["status"]);
            } else {
                $response = [
                    "status" => 200,
                    "message" => "Plan Updated Successfully",
                    "plan" => $plan
                ];
            }

            Helper::createActionLog($userId,$role,'plans','update',$before_data,$plan);
            DB::commit();
        } else {
            $response = [
                "status" => 422,
                "message" => "Plan Not Found",
            ];
        }
        return response($response, $response["status"]);
    }
    public function clonePlanWithCloning(Request $request,$id)
    {
        ini_set('max_execution_time', 0);
        DB::beginTransaction();

        DB::enableQueryLog();


            $role = $request->role;
            $userId = Auth::guard(strtolower($role))->id();
            $user = Auth::guard(strtolower($role))->user();
            $name = $user->first_name .' '.$user->last_name;
            $userWorkouts = Workout::where('uploaded_by', $userId)
                ->where('uploader', $role)
                ->with('exercises.exercise')
                ->get()
                ->keyBy('id');

            $allWorkouts = Workout::with('exercises.exercise')->get()
                ->keyBy('id');

            $userExercises = Exercise::where('uploaded_by', $userId)
                ->where('uploader', $role)
                ->with('video')
                ->get()
                ->keyBy('id');
            $allExercises = Exercise::with('video')->get()
                ->keyBy('id');

            $userVideos = Video::where('uploaded_by', $userId)
                ->where('uploader', $role)
                ->get()
                ->keyBy('id');

            $allVideos = Video::get()->keyBy('id');

            $clonedWorkouts = [];
            $clonedExercises = [];
            $clonedVideos = [];

            $cloneEntity = function ($entity, $userId, $role, &$clonedMap, $type) {
                if ($type == "video") {







                    unset($entity->laravel_through_key);
                    $key = "video_title";
                    $title = $entity->video_title ;
                }
                else
                {
                    $key = "title";
                    $title = $entity->title ;
                }

                if (isset($clonedMap[$entity->id])) {
                    return $clonedMap[$entity->id];
                }

                $cloned = $entity->replicate()->fill([
                    "$key" => $title,
                ]);

                $cloned->uploaded_by = $userId;
                $cloned->uploader = $role;
                $cloned->parent_id = $entity->id;



                $cloned->save();

                $clonedMap[$entity->id] = $cloned->id;

                return $cloned->id;
            };

            $plan = Plan::find($id);
            if(isset($plan))
            {
                $copy = $plan->replicate()->fill([
                    "title" => $plan->title . " By $name"
                ]);
                $copy->uploader = $role;
                $copy->uploaded_by = $userId;
                $copy->parent_id = $id;
                $copy->is_active = 1;
                $copy->visibility_type = $request->visibility_type ?? "Public";
                $copy->save();
                $planWorkouts = [];
                $workoutExercisePivot = [];
                $exerciseVideoPivot = [];
                $checkedWorkout = [];
                $checkedExercise = [];
                $checkedVideo = [];

                foreach ($plan->workouts as $key=>$workoutData) {

                    $workoutId = $workoutData['workout_id'] ?? null;
                    if($workoutId)
                    {
                        $workout = $userWorkouts->get($workoutData['workout_id']);

                        if (!$workout) {
                            $workout = $userWorkouts->firstWhere('parent_id', $workoutData['workout_id']);

                            if (!$workout) {
                                $workout = $allWorkouts->get($workoutData['workout_id']);

                                if (!$workout) {
                                    $workout = Workout::with('exercises.exercise')->find($workoutData['workout_id']);
                                    $workoutId = $cloneEntity($workout, $userId, $role, $clonedWorkouts, 'workout');
                                } else {
                                    $workoutId = $cloneEntity($workout, $userId, $role, $clonedWorkouts, 'workout');
                                }
                            } else {
                                $workoutId = $workout->id;
                            }
                        } else {
                            $workoutId = $workout->id;
                        }
                    }
                    $planWorkouts[] = [
                        "plan_id" => $copy->id,
                        "phase" => $workoutData['phase'] ?? null,
                        "week" => $workoutData['week'] ?? null,
                        "day" => $workoutData['day'] ?? null,
                        "workout_id" => $workoutData['is_rest'] == 1 ? null : $workoutId,
                        "is_rest" => $workoutData['is_rest'] ?? 0,
                        "sort" => $workoutData['sort'] ?? $key + 1,
                        "created_at" => Carbon::now(),
                        "updated_at" => Carbon::now()
                    ];

                    if(isset($workoutData['workout_id']) && !$userWorkouts->get($workoutData['workout_id']) && !$userWorkouts->firstWhere('parent_id', $workoutData['workout_id']))
                    {
                        if(!in_array($workoutId,$checkedWorkout))
                        {
                            $exercises = $workout->exercises;

                            foreach ($exercises as $exerciseData) {

                                    if(isset($exerciseData['is_rest']) && $exerciseData['is_rest'] == 1)
                                    {
                                        $workoutExercisePivot[] = [
                                            'workout_id' => $workoutId,
                                            'exercise_id' => null,
                                            'is_rest' => 1,
                                            "type" => $exerciseData['type'] ?? null,
                                            "min" => $exerciseData['min'] ?? null,
                                            "sec" => $exerciseData['sec'] ?? null,
                                            "set" => $exerciseData['set'] ?? null,
                                            "rep" => $exerciseData['rep'] ?? null,
                                            "rest_min" => $exerciseData['rest_min'] ?? null,
                                            "rest_sec" => $exerciseData['rest_sec'] ?? null,
                                            "is_stag" => $exerciseData['is_stag'] ?? null,
                                            "stag" => $exerciseData['stag'] ?? null,
                                            "stagger" => isset($exerciseData['stagger']) ? json_encode($exerciseData['stagger']) : null,
                                            'superset'=> $exerciseData['superset'] ?? null,
                                            "sort" => $exerciseData['sort'] ?? null,
                                            "created_at" => Carbon::now(),
                                            "updated_at" => Carbon::now()
                                        ];
                                    }
                                    if (isset($exerciseData['exercise']['id'])) {
                                        if(!in_array($exerciseData['exercise']['id'],$checkedExercise))
                                        {
                                            $exercise = $userExercises->get($exerciseData['exercise']['id']);

                                            if (!$exercise) {
                                                $exercise = $userExercises->firstWhere('parent_id', $exerciseData['exercise']['id']);
                                                if(!$exercise)
                                                {
                                                    $exercise = $allExercises->get($exerciseData['exercise']['id']);

                                                    if (!$exercise) {
                                                        $exercise = Exercise::with('video')->find($exerciseData['exercise']['id']);
                                                        $exerciseId = $cloneEntity($exercise, $userId, $role, $clonedExercises, 'exercise');
                                                    } else {
                                                        $exerciseId = $cloneEntity($exercise, $userId, $role, $clonedExercises, 'exercise');
                                                    }
                                                }
                                                else
                                                {
                                                    $exerciseId = $exercise->id;
                                                }
                                            } else {
                                                $exerciseId = $exercise->id;
                                            }

                                                $workoutExercisePivot[] = [
                                                    'workout_id' => $workoutId,
                                                    'exercise_id' => $exerciseData['is_rest'] == 1 ? null : $exerciseId,
                                                    'is_rest' => $exerciseData['is_rest'] ?? 0,
                                                    "type" => $exerciseData['type'] ?? null,
                                                    "min" => $exerciseData['min'] ?? null,
                                                    "sec" => $exerciseData['sec'] ?? null,
                                                    "set" => $exerciseData['set'] ?? null,
                                                    "rep" => $exerciseData['rep'] ?? null,
                                                    "rest_min" => $exerciseData['rest_min'] ?? null,
                                                    "rest_sec" => $exerciseData['rest_sec'] ?? null,
                                                    "is_stag" => $exerciseData['is_stag'] ?? null,
                                                    "stag" => $exerciseData['stag'] ?? null,
                                                    "stagger" => isset($exerciseData['stagger']) ? json_encode($exerciseData['stagger']) : null,
                                                    'superset'=> $exerciseData['superset'] ?? null,
                                                    "sort" => $exerciseData['sort'] ?? null,
                                                    "created_at" => Carbon::now(),
                                                    "updated_at" => Carbon::now()
                                                ];


                                                if (isset($exercise['video'])) {
                                                        if(!in_array($exercise['video']['id'],$checkedVideo))
                                                        {
                                                            $videoData = $exercise['video'];

                                                            if (isset($videoData['id'])) {
                                                                $video = $userVideos->get($videoData['id']);

                                                                if (!$video) {
                                                                    $video = $userVideos->firstWhere('parent_id', $videoData['id']);
                                                                    if(!$video)
                                                                    {
                                                                        $video = $allVideos->get($videoData['id']);
                                                                        if (!$video) {
                                                                            $video = Video::find($videoData['id']);
                                                                            $videoId = $cloneEntity($video, $userId, $role, $clonedVideos, 'video');
                                                                        } else {
                                                                            $videoId = $cloneEntity($video, $userId, $role, $clonedVideos, 'video');
                                                                        }
                                                                    }
                                                                    else {
                                                                        $videoId = $video->id;
                                                                    }
                                                                } else {
                                                                    $videoId = $video->id;
                                                                }
                                                                $checkedVideo[] = $videoId;
                                                                    $exerciseVideoPivot[] = [
                                                                        'exercise_id' => $exerciseId,
                                                                        'video_id' => $videoId,
                                                                    ];
                                                            }
                                                        }
                                                }

                                            $checkedExercise[] = $exerciseId;
                                        }
                                    }

                            }
                        }
                    }
                    $checkedWorkout[] = $workoutId;
                }
                info('planWorkouts:', ['planWorkouts' => $planWorkouts]);
                info('workoutExercisePivot:', ['workoutExercisePivot' => $workoutExercisePivot]);
                info('exerciseVideoPivot:', ['exerciseVideoPivot' => $exerciseVideoPivot]);
                info($exerciseVideoPivot);
                if (!empty($planWorkouts)) {
                    PlanWorkout::insert($planWorkouts);
                }

                if (!empty($workoutExercisePivot)) {
                    WorkoutExercise::insert($workoutExercisePivot);
                }

                if (!empty($exerciseVideoPivot)) {
                    ExerciseVideo::insert($exerciseVideoPivot);
                }


                $plan->load('upload_by:id,first_name,last_name,profile_image');
                $weeksByPhase = $plan->totalWeeks();
                $plan['total_weeks'] = $weeksByPhase;
                $copy->load([
                    "upload_by:id,first_name,last_name,profile_image",
                    "workouts" => function ($query) {
                        $query->with(["workout.exercise" => function ($subquery) {
                            $subquery->with('video')
                                ->leftJoin('exercises', 'workout_exercises.exercise_id', '=', 'exercises.id')
                                ->select(
                                    'workout_exercises.*',
                                    'workout_exercises.id as workout_exercise_id',
                                    'exercises.id as id',
                                    'exercises.title as title',
                                    'exercises.tags as tags',
                                    'exercises.description as description'
                                );
                        }]);
                    },
                    'users:users.id,first_name,last_name,profile_image',
                    'organizations:organizations.id,name,logo'
                ]);
                $weeksByPhase = $copy->totalWeeks();
                $copy['total_weeks'] = $weeksByPhase;
                Helper::createActionLog($userId,$role,'plans','clone',$copy,$plan);
                DB::commit();

                $queries = DB::getQueryLog();
                return response()->json([
                    'success' => true,
                    'message' => 'Plan Ready For Assign',
                    'plan' => $copy,
                    'queries' => $queries, // Optionally include queries for debugging
                ]);

            }
            else
            {
                $response = [
                    "status" => 422,
                    "message" => "Plan Not Found"
                ];

                return response($response,$response['status']);
            }
    }
    public function clonePlan(Request $request, $id)
    {
        ini_set('max_execution_time',0);
        DB::enableQueryLog();
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();

        $plan = Plan::with('workouts.workout.exercises.video','workouts.workout.exercises.exercise.video')->find($id);

        if (isset($plan)) {

            if($plan->uploaded_by == $userId && $plan->uploader == $role)
            {
                $response = [
                    "status" => 422,
                    "message" => "You don't clone it because it is already created by you",
                ];
                return response($response, $response["status"]);
            }

            DB::beginTransaction();
            $copy = $plan->replicate()->fill(
                [
                    'title' => $plan->title
                ]
            );
            $copy->parent_id = $id;
            $copy->uploaded_by = $userId;
            $copy->uploader = $role;
            $copy->save();


            foreach ($plan->workouts as $key => $item) {
                $workout = null;
                $workout = $item->workout;
                if ($workout) {
                    $clonedWorkout = null;
                    $existingCloneWorkout = Workout::where('parent_id',$workout->id)->where('uploaded_by',$userId)->where('uploader',$role)->first();
                    if(!isset($existingCloneWorkout))
                    {
                        if ($workout && $workout->uploaded_by == $userId && $workout->uploader == $role) {
                            $clonedWorkout = $workout;
                        } elseif ($workout) {
                            $clonedWorkout = $workout->replicate()->fill(
                                [
                                    'title' => $workout->title
                                ]
                            );
                            $clonedWorkout->uploaded_by = $userId;
                            $clonedWorkout->uploader = $role;
                            $clonedWorkout->parent_id = $workout->id;
                            $clonedWorkout->save();
                        }
                    }
                    else{
                        $clonedWorkout = $existingCloneWorkout;
                    }

                    foreach ($workout->exercises as $key =>$eitem) {
                        $exercise = null;
                        $exercise = $eitem->exercise;
                        if($exercise)
                        {
                            $clonedExercise = null;
                            $existingClonedExercise = Exercise::where('parent_id', $exercise->id)
                            ->where('uploaded_by', $userId)
                            ->where('uploader', $role)
                            ->first();
                            if(!isset($existingClonedExercise))
                            {
                                if ($exercise->uploaded_by == $userId && $exercise->uploader == $role) {
                                    $clonedExercise = $exercise;
                                } else {
                            $clonedExercise = $exercise->replicate()->fill(
                                [
                                    'title' => $exercise->title
                                ]
                            );
                            $clonedExercise->uploaded_by = $userId;
                            $clonedExercise->uploader = $role;
                            $clonedExercise->parent_id = $exercise->id;
                            $clonedExercise->save();
                                }
                            }
                            else
                            {
                                $clonedExercise = $existingClonedExercise;
                            }
                            $video = null;
                            $video = $exercise->video;
                            if($video)
                            {
                                $clonedVideo = null;
                                $clonedVideo = Video::where('parent_id', $video->id)
                                ->where('uploaded_by', $userId)
                                ->where('uploader', $role)
                                ->first();

                                if (!$clonedVideo) {
                                    if ($video && $video->uploaded_by == $userId && $video->uploader == $role) {
                                        $clonedVideo = $video;
                                    }
                                    else
                                    {
                                        if ($video->video_format == "file") {
                                            $videoFile = basename($video->video_file);
                                            $videoFilename = time() . rand(111, 699) . '.' . pathinfo($video->video_file, PATHINFO_EXTENSION);

                                            $originalVideoPath = public_path('upload/videos/' . $videoFile);
                                            $newVideoPath = public_path('upload/videos/' . $videoFilename);

                                            $clonedVideoFile = null;


                                            if (is_file($originalVideoPath)) {
                                                $newVideoPath = public_path('upload/videos/' . $videoFile);

                                                if (copy($originalVideoPath, $newVideoPath)) {
                                                    $clonedVideoFile = $videoFilename;
                                                }

                                            }
                                        }

                                        $thumbnailFile = basename($video->video_thumbnail);
                                        $thumbnailFilename = time() . rand(111, 699) . '_thumb.' . pathinfo($video->video_thumbnail, PATHINFO_EXTENSION);

                                        $originalThumbnailPath = public_path('upload/videos/thumbnails/' . $thumbnailFile);

                                        $clonedThumbnailFile = null;

                                        if (is_file($originalThumbnailPath)) {
                                            $newThumbnailPath = public_path('upload/videos/thumbnails/' . $thumbnailFilename);

                                            if (copy($originalThumbnailPath, $newThumbnailPath)) {
                                                $clonedThumbnailFile = $thumbnailFilename;
                                            }

                                        }

                                        $clonedVideo = $video->replicate()->fill(
                                            [
                                                'video_title' => $video->video_title
                                                ]
                                        );
                                        unset($clonedVideo->laravel_through_key);
                                        $clonedVideo->video_file = $clonedVideoFile ?? null;
                                        $clonedVideo->video_thumbnail = $clonedThumbnailFile ?? null;
                                        $clonedVideo->uploaded_by = $userId;
                                        $clonedVideo->uploader = $role;
                                        $clonedVideo->parent_id = $video->id;
                                        $clonedVideo->save();
                                    }
                                }


                                if($clonedExercise)
                                {
                                    $clonedExercise->videos_pivot()->sync($clonedVideo->id);
                                }
                            }
                        }

                            $clonedWorkoutExercise = $eitem->replicate();
                            $clonedWorkoutExercise->workout_id = $clonedWorkout ? $clonedWorkout->id : null;
                            $clonedWorkoutExercise->exercise_id = $eitem['is_rest'] ? null : ($clonedExercise ? $clonedExercise->id : null);
                            $clonedWorkoutExercise->stagger = $eitem['stagger'] ?? null;
                            $clonedWorkoutExercise->is_stag = $eitem['is_stag'] ?? null;
                            $clonedWorkoutExercise->superset = $eitem['superset'] ?? null;
                            $clonedWorkoutExercise->save();
                    }
                }
                $clonedPlanWorkout = $item->replicate();
                $clonedPlanWorkout->plan_id = $copy->id;
                $clonedPlanWorkout->workout_id = $item['is_rest'] ? null : ($clonedWorkout ? $clonedWorkout->id : null);
                $clonedPlanWorkout->save();
            }
            $plan->load('upload_by:id,first_name,last_name,profile_image');
            $weeksByPhase = $plan->totalWeeks();
            $plan['total_weeks'] = $weeksByPhase;
            $copy->load([
                "upload_by:id,first_name,last_name,profile_image",
                "workouts" => function ($query) {
                    $query->with(["workout.exercise" => function ($subquery) {
                        $subquery->with('video')
                            ->leftJoin('exercises', 'workout_exercises.exercise_id', '=', 'exercises.id')
                            ->select(
                                'workout_exercises.*',
                                'workout_exercises.id as workout_exercise_id',
                                'exercises.id as id',
                                'exercises.title as title',
                                'exercises.tags as tags',
                                'exercises.description as description'
                            );
                    }]);
                },
                'users:users.id,first_name,last_name,profile_image',
                'organizations:organizations.id,name,logo'
            ]);
            $weeksByPhase = $copy->totalWeeks();
            $copy['total_weeks'] = $weeksByPhase;
            Helper::createActionLog($userId,$role,'plans','clone',$copy,$plan);
            DB::commit();
            $response = [
                "status" => 200,
                "message" => "Plan cloned successfully",
                "plan" => $copy
            ];
        }
        else
        {
            $response = [
                "status" => 422,
                "message" => "Plan Not Found",
            ];
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $queryCount = count($queries);
        $totalTime = array_sum(array_column($queries, 'time'));
        $response['debug'] = [
            'query_count' => $queryCount,
            'total_query_time_ms' => $totalTime,
            'queries' => $queries
        ];

        return response($response, $response["status"]);
    }














    public function getPlans(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $plans = Plan::with('upload_by:id,first_name,last_name,profile_image')->when(!$request->filled('organization_id') && $role == "Coach",function($query) use ($request,$userId,$role){
            $query->where(function($subquery) use ($request,$userId,$role){
                $subquery->where('uploaded_by',$userId)
                ->where('uploader',$role);
            })->orWhere('visibility_type','Public');
        })->when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('title', 'LIKE', '%' . $request->search . '%');
            });
        })
        ->when($request->filled('visibility_type'),function($query) use ($request){
            $query->where('visibility_type',$request->query('visibility_type'));
        })
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->query('status') == 'Active') {
                    $query->where('is_active', 1);
                } else if ($request->query('status') == 'Blocked') {
                    $query->where('is_active', 0);
                }
            })
            ->when($request->filled('uploaded_by'), function ($query) use ($request) {
                $query->where('uploader', $request->query('uploaded_by'));
            })
            ->when($request->filled('coach_id'), function ($query) use ($request) {
                $query->where('uploader', 'Coach')
                    ->where('uploaded_by', $request->query('coach_id'));
            })
            ->when($request->filled('organization_id'), function ($query) use ($request) {
                $query->whereHas('organizations', function($subquery) use ($request){
                    $subquery->where('organization_id',$request->query('organization_id'));
                });
            })
            ->latest();

        $response = Pagination::paginate($request, $plans, "plans");

        foreach ($response['plans'] as $plan) {
            $plan->total_weeks = $plan->totalWeeks();
        }

        return response($response, $response["status"]);
    }

    public function getPlan(Request $request, $id)
    {
        $plan = Plan::with([
            "upload_by:id,first_name,last_name,profile_image",
            "workouts" => function ($query) {
                $query->with(["workout.exercise" => function ($subquery) {
                    $subquery->with('video')
                        ->leftJoin('exercises', 'workout_exercises.exercise_id', '=', 'exercises.id')
                        ->select(
                            'workout_exercises.*',
                            'workout_exercises.id as workout_exercise_id',
                            'exercises.id as id',
                            'exercises.title as title',
                            'exercises.tags as tags',
                            'exercises.description as description'
                        );
                }]);
            },
            'users:users.id,first_name,last_name,profile_image,is_active,start_date,end_date',
            'organizations:organizations.id,name,logo,is_active,start_date,end_date'
        ])->find($id);
        if (isset($plan)) {
            $weeksByPhase = $plan->totalWeeks();
            $maxPhase = $plan->workouts->max('phase');
            $maxWeek = $plan->workouts->where('phase', $maxPhase)->max('week');
            $maxDay = $plan->workouts->where('phase', $maxPhase)->where('week', $maxWeek)->max('day');

            $plan['days'] = $maxDay;
            $plan['total_weeks'] = $weeksByPhase;
            $response = [
                "status" => 200,
                "message" => "Plan Fetched Successfully",
                "plan" => $plan
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Plan Not Found!",
            ];
        }

        return response($response, $response["status"]);
    }
    public function deletePlan(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $plan = Plan::find($id);
        if (isset($plan)) {
            AssignPlan::where('plan_id', $id)->delete();
            UserCompletedWorkout::where('plan_id',$id)->delete();
            Helper::createActionLog($userId,$role,'plans','delete',$plan,null);
            $plan->delete();
            $response = [
                "status" => 200,
                "message" => "Plan Deleted Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Plan Not Found!",
            ];
        }

        return response($response, $response["status"]);
    }

    public function assignPlan(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'required|date|before:end_date',
            'end_date' => 'required|date|after:start_date',
            'users' => 'nullable|array',
            'users.*' => 'nullable|exists:users,id',
            'organizations' => 'nullable|array',
            'organizations' => 'nullable|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $plan = Plan::find($request->plan_id);
        if(isset($plan))
        {

            $finalData = [];
            $notifications = [];
            if(isset($request->users))
            {
                foreach($request->users as $user)
                {
                    $exists = AssignPlan::where('user_id', $user)
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('start_date', [$startDate, $endDate])
                              ->orWhereBetween('end_date', [$startDate, $endDate])
                              ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                                  $subQuery->where('start_date', '<=', $startDate)
                                           ->where('end_date', '>=', $endDate);
                              });
                    })->first();

                    if (isset($exists)) {
                        $response = [
                            "status" => 422,
                            "message" => "User is already assigned a plan within the given date range: $exists->start_date to $exists->end_date."
                        ];
                        return response($response,$response['status']);
                    }
                    $finalData[] = [
                        'plan_id' => $request->plan_id,
                        'user_id' => $user,
                        'organization_id' => null,
                        'uploaded_by' => $userId,
                        'uploader' => $role,
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                    $notifications[] = [
                        "user_id" => $user,
                        "title" => "Plan Assignment",
                        "message" => "You have been assigned the plan: $plan->title",
                        "created_at" => now(),
                        "updated_at" => now(),
                    ];
                }
            }
            if(isset($request->organizations))
            {
                foreach($request->organizations as $organization)
                {
                    $exists = AssignPlan::where('organization_id', $organization)
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('start_date', [$startDate, $endDate])
                              ->orWhereBetween('end_date', [$startDate, $endDate])
                              ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                                  $subQuery->where('start_date', '<=', $startDate)
                                           ->where('end_date', '>=', $endDate);
                              });
                    })->first();

                    if (isset($exists)) {
                        $response = [
                            "status" => 422,
                            "message" => "Organization is already assigned a plan within the given date range: $exists->start_date to $exists->end_date."
                        ];
                        return response($response,$response['status']);
                    }
                    $finalData[] = [
                        'plan_id' => $request->plan_id,
                        'user_id' => null,
                        'organization_id' => $organization,
                        'uploaded_by' => $userId,
                        'uploader' => $role,
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
                $users = User::whereHas('organization', function ($query) use ($request) {
                    $query->whereIn('id', $request->organizations);
                })->pluck('id')->toArray();

                $chunks = array_chunk($users, 1500);
                foreach ($chunks as $chunk) {
                    $notifications = array_merge($notifications, array_map(function ($userId) use ($plan) {
                        return [
                            "user_id" => $userId,
                            "title" => "Plan Assignment",
                            "message" => "You have been assigned the plan: $plan->title",
                            "created_at" => now(),
                            "updated_at" => now(),
                        ];
                    }, $chunk));
                }
            }
            if (!empty($finalData)) {
                AssignPlan::insert($finalData);
            }

            if (!empty($notifications)) {
                AppNotification::insert($notifications);
                Helper::sendPush("Plan Assignment", "You have been assigned the plan: $plan->title", null, null, "Notification", null, array_column($notifications, 'user_id'));
                $plan['start_date'] = $request->start_date;
                $plan['end_date'] = $request->end_date;
                try
                {
                    Helper::sendPlanAssignment(array_column($notifications, 'user_id'),$plan);
                    $message = "Plan Successfully Assigned and Mail Sent";
                }
                catch(Exception $e)
                {
                    $message = "Plan Successfully Assigned But Mail Not Sent!";
                }
            }
            $response = [
                "status" => 200,
                "message" => $message ?? "Plan Successfully Assigned"
            ];
        }
        else
        {
            $response = [
                "status" => 422,
                "message" => "Plan Not Found!"
            ];
        }

        return response($response,$response['status']);

    }

    public function deleteAssignPlan(Request $request,$id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required_without:organization_id|exists:users,id',
            'organization_id' => 'required_without:user_id|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $assigned_plan = AssignPlan::where('plan_id',$id)
        ->when($request->filled('user_id'),function($query) use ($request){
            $query->where('user_id',$request->user_id);
        })
        ->when($request->filled('organization_id'),function($query) use ($request){
            $query->where('organization_id',$request->organization_id);
        })
        ->first();

        $userIds = [];

        if(isset($assigned_plan))
        {
            if ($request->filled('organization_id')) {
                $organization = Organization::with('employees')->find($request->organization_id);
                if ($organization) {
                    $userIds = $organization->employees->pluck('id')->toArray();
                }
            } else {
                $userIds[] = $request->user_id;
            }

            if (!empty($userIds)) {
                UserCompletedWorkout::where('plan_id', $id)->whereIn('user_id', $userIds)->delete();
            }
            $data = $assigned_plan;
            $assigned_plan->delete();
            $response = [
                "status" => 200,
                "message" => "Assigned plan deleted successfully.",
                "data" => $data
            ];
        }
        else
        {
            $response = [
                "status" => 422,
                "message" => "Assigned plan Not Found.",
            ];
        }

        return response($response, $response['status']);
    }

    public function getPlanDropDown(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $limit = $request->query('limit',10);
        $plans = Plan::with('upload_by:id,first_name,last_name,profile_image')
        ->when($role == "Coach",function($query) use ($userId){
            $query->where('uploader','Coach')
            ->where('uploaded_by',$userId);
        })
        ->when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('title', 'LIKE', '%' . $request->search . '%');
            });
        })
        ->where('is_active',1)->limit($limit)->select('id','title','uploader','uploaded_by')->latest()->get();

        $response = [
            "status" => 200,
            "message" => "Plans Fetched Successfully",
            "plans" => $plans
        ];

        return response($response, $response["status"]);
    }
}
