<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseCooldown;
use App\Models\ExerciseWarmup;
use App\Models\User;
use App\Models\Video;
use App\Models\Workout;
use App\Models\WorkoutCalendar;
use App\Models\WorkoutEquipment;
use App\Models\WorkoutExercise;
use App\Models\PlanWorkout;
use App\Models\UserCompletedWorkout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WorkoutController extends Controller
{
    public function addWorkout(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'exercises' => 'required|array|min:1',
            'exercises.*.superset' => 'array|min:2',
            'exercises.*.is_rest' => 'required_without:exercises.*.superset|in:0,1',
            'exercises.*.id' => 'required_if:exercises.*.is_rest,0|exists:exercises,id,deleted_at,NULL|nullable',
            'exercises.*.type' => 'required_if:exercises.*.is_rest,0|in:Duration,Sets',
            'exercises.*.min' => [
                'nullable',
                'numeric',
            ],
            'exercises.*.sec' => [
                'nullable',
                'numeric',
            ],
            'exercises.*.superset.*.is_rest' => 'required|in:0,1',
            'exercises.*.superset.*.id' => 'required_if:exercises.*.superset.*.is_rest,0|exists:exercises,id,deleted_at,NULL',
            'exercises.*.superset.*.type' => 'required_if:exercises.*.superset.*.is_rest,0|in:Duration,Sets',
            'sort' => 'numeric'
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
        $workout = Workout::create($request->toArray());
        $workout->uploader = $role;
        $workout->uploaded_by = $userId;
        if (isset($request->exercises)) {
            $values = [];
            $exercises = $request->exercises;
            $exerciseCount = count($exercises);

            $firstExercise = $exercises[0];
            $lastExercise = $exercises[$exerciseCount - 1];

            $sort = 1;
            $count = 1;
            foreach ($request->exercises as $key => $exercise) {
                if(isset($exercise['superset']) && is_array($exercise['superset']))
                {
                    $exerciseSuperSetCount = count($exercise['superset']);
                    $firstSupersetExercise = $exercise['superset'][0];
                    $lastSupersetExercise = $exercise['superset'][$exerciseSuperSetCount - 1];
                    foreach($exercise['superset'] as $key=> $superset)
                    {

                        $exerciseId = $superset['id'] ?? null;
                        if($exerciseId)
                        {
                            $existingExercise = Exercise::with('videos','video')->find($exerciseId);
                            if(isset($existingExercise))
                            {
                                $existingClonedExercise = Exercise::with('videos','video')->where('parent_id', $existingExercise->id)
                                ->where('uploaded_by', $userId)
                                ->where('uploader', $role)
                                ->first();
                                if(!isset($existingClonedExercise))
                                {
                                    if ($existingExercise->uploaded_by == $userId && $existingExercise->uploader == $role) {
                                        $clonedExercise = $existingExercise;
                                    } else {
                                        $clonedExercise = $existingExercise->replicate()->fill(
                                            [
                                                'title' => $existingExercise->title
                                            ]
                                        );
                                        $clonedExercise->uploaded_by = $userId;
                                        $clonedExercise->uploader = $role;
                                        $clonedExercise->parent_id = $existingExercise->id;
                                        $clonedExercise->save();
                                    }
                                }
                                else
                                {
                                    $clonedExercise = $existingClonedExercise;
                                }
                                $exerciseId = $clonedExercise->id;

                                $video = $existingExercise->video;
                                if($video)
                                {
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











                                            $clonedVideo = $video->replicate()->fill(
                                                [
                                                    'video_title' => $video->video_title
                                                ]
                                            );
                                            unset($clonedVideo->laravel_through_key);
                                            $clonedVideo->uploaded_by = $userId;
                                            $clonedVideo->uploader = $role;
                                            $clonedVideo->parent_id = $video->id;
                                            $clonedVideo->save();
                                        }
                                    }

                                    $clonedExercise->videos_pivot()->sync($clonedVideo->id);
                                }
                            }
                        }
                        if(isset($superset['is_stag']) && $superset['is_stag'] && isset($superset['repsArray']) && is_array($superset['repsArray']))
                        {
                                $values[] = [
                                    "workout_id" => $workout->id,
                                    "exercise_id" => $exerciseId,
                                    "type" => $superset['type'] ?? null,
                                    "min" => $superset['min'] ?? null,
                                    "sec" => $superset['sec'] ?? null,
                                    "set" => $superset['set'] ?? null,
                                    "rep" => $superset['rep'] ?? null,
                                    "is_rest" => $superset['is_rest'] ?? 0,
                                    "rest_min" => $superset['rest_min'] ?? null,
                                    "rest_sec" => $superset['rest_sec'] ?? null,
                                    "is_stag" => 1,
                                    "stag" => $superset['stag'] ?? null,
                                    "stagger" => isset($superset['repsArray']) ? json_encode($superset['repsArray']) : null,
                                    'superset'=> $count,
                                    "sort" => $sort,
                                    "created_at" => Carbon::now(),
                                    "updated_at" => Carbon::now()
                                ];
                                $sort++;
                        }
                        else
                        {

                            $values[] = [
                                "workout_id" => $workout->id,
                                "exercise_id" => $exerciseId,
                                "type" => $superset['type'] ?? null,
                                "min" => $superset['min'] ?? null,
                                "sec" => $superset['sec'] ?? null,
                                "set" => $superset['set'] ?? null,
                                "rep" => $superset['rep'] ?? null,
                                "is_rest" => $superset['is_rest'] ?? 0,
                                "rest_min" => $superset['rest_min'] ?? null,
                                "rest_sec" => $superset['rest_sec'] ?? null,
                                "sort" => $sort,
                                "is_stag" => 0,
                                "stag" => null,
                                "stagger" => null,
                                'superset'=> $count,
                                "created_at" => Carbon::now(),
                                "updated_at" => Carbon::now()
                            ];
                            $sort++;
                        }
                    }
                    $count++;
                }
                else if(isset($exercise['is_stag']) && $exercise['is_stag'] && isset($exercise['repsArray']) && is_array($exercise['repsArray']))
                {
                        $exerciseId = $exercise['id'] ?? null;
                        if($exerciseId)
                        {
                            $existingExercise = Exercise::find($exerciseId);
                            if(isset($existingExercise))
                            {
                                $existingClonedExercise = Exercise::where('parent_id', $existingExercise->id)
                                ->where('uploaded_by', $userId)
                                ->where('uploader', $role)
                                ->first();
                                if(!isset($existingClonedExercise))
                                {
                                    if ($existingExercise->uploaded_by == $userId && $existingExercise->uploader == $role) {
                                        $clonedExercise = $existingExercise;
                                    } else {
                                        $clonedExercise = $existingExercise->replicate()->fill(
                                            [
                                                'title' => $existingExercise->title
                                            ]
                                        );
                                        $clonedExercise->uploaded_by = $userId;
                                        $clonedExercise->uploader = $role;
                                        $clonedExercise->parent_id = $existingExercise->id;
                                        $clonedExercise->save();
                                    }
                                }
                                else
                                {
                                    $clonedExercise = $existingClonedExercise;
                                }
                                $exerciseId = $clonedExercise->id;

                                $video = $existingExercise->video;
                            if($video)
                            {
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











                                        $clonedVideo = $video->replicate()->fill(
                                            [
                                                'video_title' => $video->video_title
                                            ]
                                        );
                                        unset($clonedVideo->laravel_through_key);
                                        $clonedVideo->uploaded_by = $userId;
                                        $clonedVideo->uploader = $role;
                                        $clonedVideo->parent_id = $video->id;
                                        $clonedVideo->save();
                                    }
                                }

                                $clonedExercise->videos_pivot()->sync($clonedVideo->id);
                            }
                            }
                        }
                        $values[] = [
                            "workout_id" => $workout->id,
                            "exercise_id" => $exerciseId,
                            "type" => $exercise['type'] ?? null,
                            "min" => $exercise['min'] ?? null,
                            "sec" => $exercise['sec'] ?? null,
                            "set" => $exercise['set'] ?? null,
                            "rep" => $exercise['rep'] ?? null,
                            "is_rest" => $exercise['is_rest'] ?? 0,
                            "rest_min" => $exercise['rest_min'] ?? null,
                            "rest_sec" => $exercise['rest_sec'] ?? null,
                            "is_stag" => 1,
                            "stag" => $exercise['stag'] ?? null,
                            "stagger" => isset($exercise['repsArray']) ? json_encode($exercise['repsArray']) : null,
                            'superset'=> null,
                            "sort" => $sort,
                            "created_at" => Carbon::now(),
                            "updated_at" => Carbon::now()
                        ];
                        $sort++;
                }
                else
                {

                    $exerciseId = $exercise['id'] ?? null;
                    if($exerciseId)
                    {
                        $existingExercise = Exercise::find($exerciseId);
                        if(isset($existingExercise))
                        {
                            $existingClonedExercise = Exercise::where('parent_id', $existingExercise->id)
                            ->where('uploaded_by', $userId)
                            ->where('uploader', $role)
                            ->first();
                            if(!isset($existingClonedExercise))
                            {
                                if ($existingExercise->uploaded_by == $userId && $existingExercise->uploader == $role) {
                                    $clonedExercise = $existingExercise;
                                } else {
                                    $clonedExercise = $existingExercise->replicate()->fill(
                                        [
                                            'title' => $existingExercise->title
                                        ]
                                    );
                                    $clonedExercise->uploaded_by = $userId;
                                    $clonedExercise->uploader = $role;
                                    $clonedExercise->parent_id = $existingExercise->id;
                                    $clonedExercise->save();
                                }
                            }
                            else
                            {
                                $clonedExercise = $existingClonedExercise;
                            }
                            $exerciseId = $clonedExercise->id;

                            $video = $existingExercise->video;
                                if($video)
                                {
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











                                            $clonedVideo = $video->replicate()->fill(
                                                [
                                                    'video_title' => $video->video_title
                                                ]
                                            );
                                            unset($clonedVideo->laravel_through_key);
                                            $clonedVideo->uploaded_by = $userId;
                                            $clonedVideo->uploader = $role;
                                            $clonedVideo->parent_id = $video->id;
                                            $clonedVideo->save();
                                        }
                                    }

                                    $clonedExercise->videos_pivot()->sync($clonedVideo->id);
                                }
                        }
                    }

                    $type = $exercise['type'] ?? null;
                    $set = null;
                    $rep = null;
                    $min = null;
                    $sec = null;
                    if(isset($type) && $type == "Duration" )
                    {
                        $set = null;
                        $rep = null;
                        $min = $exercise['min'];
                        $sec = $exercise['sec'];
                    }
                    if(isset($type) && $type == "Sets" )
                    {
                        $set = $exercise['set'];
                        $rep = $exercise['rep'];
                        $min = null;
                        $sec = null;
                    }

                    $values[] = [
                        "workout_id" => $workout->id,
                        "exercise_id" => $exerciseId,
                        "type" => $type,
                        "min" => $min,
                        "sec" => $sec,
                        "set" => $set,
                        "rep" => $rep,
                        "is_rest" => $exercise['is_rest'] ?? 0,
                        "rest_min" => $exercise['rest_min'] ?? null,
                        "rest_sec" => $exercise['rest_sec'] ?? null,
                        "sort" => $sort,
                        "is_stag" => 0,
                        "stag" => null,
                        "stagger" => null,
                        "superset" => null,
                        "created_at" => Carbon::now(),
                        "updated_at" => Carbon::now()
                    ];
                    $sort++;
                }
            }
            WorkoutExercise::insert($values);
        }
        $workout->is_active = 1;
        $workout->visibility_type = $request->visibility_type ?? "Public";
        $workout->save();

        $workout->load('upload_by:id,first_name,last_name,profile_image','exercise.video');
        Helper::createActionLog($userId,$role,'workouts','add',null,$workout);
        DB::commit();
        $response = [
            "status" => 200,
            "message" => "Workout Added Successfully",
            "workout" => $workout
        ];
        return response($response, $response["status"]);
    }
    public function cloneWorkout(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();

        $workout = Workout::with(['exercises'=>function($query){
            $query->with(['exercise'=>function($query){
                $query->with('video','videos');
            }]);
        }])->find($id);

        if (isset($workout)) {
            DB::beginTransaction();
            $copy = $workout->replicate()->fill(
                [
                    'title' => $workout->title
                ]
            );
            $copy->parent_id = $id;
            $copy->uploaded_by = $userId;
            $copy->uploader = $role;
            $copy->save();

            foreach ($workout->exercises as $item) {
                $exercise = $item->exercise;

                if($exercise)
                {
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

                    $video = $exercise->video;
                    if($video)
                    {
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













                                $clonedVideo = $video->replicate()->fill(
                                    [
                                        'video_title' => $video->video_title
                                        ]
                                );
                                unset($clonedVideo->laravel_through_key);
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

                $clonedWorkoutExercise = $item->replicate();
                $clonedWorkoutExercise->workout_id = $copy->id;
                $clonedWorkoutExercise->exercise_id = $item['is_rest'] ? null : ($clonedExercise ? $clonedExercise->id : null);
                $clonedWorkoutExercise->stagger = $item['stagger'] ?? null;
                $clonedWorkoutExercise->is_stag = $item['is_stag'] ?? null;
                $clonedWorkoutExercise->superset = $item['superset'] ?? null;
                $clonedWorkoutExercise->save();
            }

            $workout->load('upload_by:id,first_name,last_name,profile_image');
            $copy->load([
                "upload_by:id,first_name,last_name,profile_image",
                "exercises" => function($query) {
                    $query->with('videos')
                        ->leftJoin('exercises', 'workout_exercises.exercise_id', 'exercises.id')
                        ->select(
                            'workout_exercises.*',
                            'workout_exercises.id as workout_exercise_id',
                            'exercises.id as id',
                            'exercises.title as title',
                            'exercises.tags as tags',
                            'exercises.description as description',
                        )->orderBy('workout_exercises.sort','asc');
                }
            ]);
            $exercises = $copy->exercises;
            unset($copy->exercises);
            $highestSort = $exercises->max('sort');
            $supersetArray = [];

            for ($i = 1; $i <= $highestSort; $i++) {
                $exercise = $exercises[$i - 1];
                if ($exercise->stagger) {
                    $exercise->repsArray = $exercise->stagger;
                }

                if (isset($exercise['superset'])) {
                    $supersetKey = 'superset' . $exercise['superset'];
                    if (!isset($supersetArray[$supersetKey])) {
                        $supersetArray[$supersetKey] = [];
                    }



                        $supersetArray[$supersetKey][] = $exercise->toArray();
                } else {


                        $supersetArray[] = $exercise->toArray();
                }

            }

            $finalArray = array_values($supersetArray);
            $copy->exercises = collect($finalArray);
            Helper::createActionLog($userId,$role,'workouts','clone',$copy,$workout);
            DB::commit();
            $response = [
                "status" => 200,
                "message" => "Workout Cloned successfully",
                "workout" => $copy
            ];
        }
        else
        {
            $response = [
                "status" => 422,
                "message" => "Workout Not Found",
            ];
        }


        return response($response, $response["status"]);
    }
    public function updateWorkout(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'title' => [
                'string',
                'max:255',
            ],
            'exercises' => 'required|array|min:1',
            'exercises.*.is_rest' => 'required_without:exercises.*.superset|in:0,1',
            'exercises.*.id' => 'required_if:exercises.*.is_rest,0|exists:exercises,id,deleted_at,NULL|nullable',
            'exercises.*.type' => 'required_if:exercises.*.is_rest,0|in:Duration,Sets',
            'exercises.*.min' => [
                'nullable',
                'numeric',
            ],
            'exercises.*.sec' => [
                'nullable',
                'numeric',

            ],
            'exercises.*.repsArray' => 'nullable|array',
            'exercises.*.superset.*.is_rest' => 'required|in:0,1',
            'exercises.*.superset.*.id' => 'required_if:exercises.*.superset.*.is_rest,0|exists:exercises,id,deleted_at,NULL|nullable',
            'exercises.*.superset.*.type' => 'required_if:exercises.*.superset.*.is_rest,0|in:Duration,Sets',
            'sort' => 'numeric',
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

        $workout = Workout::with('upload_by:id,first_name,last_name,profile_image')->find($id);
        if (isset($workout)) {
            if ($request->has('deleted_ids') && is_array($request->deleted_ids)) {
                $deletedIds = $request->deleted_ids;

                UserCompletedWorkout::whereIn('workout_exercise_id', $deletedIds)->delete();
                WorkoutExercise::whereIn('id', $deletedIds)->delete();
            }

            $before_data = $workout->replicate();
            $workout->fill($request->toArray());
            $onlyIsActive = $request->only(['is_active']) == $request->all();
            $message = '';
            if ($request->filled('is_active')) {
                $workout->is_active = $request->is_active;
                $message = $request->is_active == 1 ? 'Workout Active Successfully' : 'Workout Blocked Successfully';
            }
            $workout->uploader = $role;
            $workout->uploaded_by = $userId;
            if (isset($request->exercises)) {
                $values = [];
                $exercises = $request->exercises;
                $exerciseCount = count($exercises);

                $firstExercise = $exercises[0];
                $lastExercise = $exercises[$exerciseCount - 1];
                $sort = 1;
                $count = 1;
                foreach ($request->exercises as $key => $exercise) {
                    if(isset($exercise['superset']) && is_array($exercise['superset']))
                    {
                        $exerciseSuperSetCount = count($exercise['superset']);
                        $firstSupersetExercise = $exercise['superset'][0];
                        $lastSupersetExercise = $exercise['superset'][$exerciseSuperSetCount - 1];
                        foreach($exercise['superset'] as $key=> $superset)
                        {

                            $exerciseId = $superset['id'] ?? null;
                            if($exerciseId)
                            {
                                $existingExercise = Exercise::with('videos','video')->find($exerciseId);
                                if(isset($existingExercise))
                                {
                                    $existingClonedExercise = Exercise::with('videos','video')->where('parent_id', $existingExercise->id)
                                    ->where('uploaded_by', $userId)
                                    ->where('uploader', $role)
                                    ->first();
                                    if(!isset($existingClonedExercise))
                                    {
                                        if ($existingExercise->uploaded_by == $userId && $existingExercise->uploader == $role) {
                                            $clonedExercise = $existingExercise;
                                        } else {
                                            $clonedExercise = $existingExercise->replicate()->fill(
                                                [
                                                    'title' => $existingExercise->title
                                                ]
                                            );
                                            $clonedExercise->uploaded_by = $userId;
                                            $clonedExercise->uploader = $role;
                                            $clonedExercise->parent_id = $existingExercise->id;
                                            $clonedExercise->save();
                                        }
                                    }
                                    else
                                    {
                                        $clonedExercise = $existingClonedExercise;
                                    }
                                    $exerciseId = $clonedExercise->id;

                                    $video = $existingExercise->video;
                                    if($video)
                                    {
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











                                                $clonedVideo = $video->replicate()->fill(
                                                    [
                                                        'video_title' => $video->video_title
                                                    ]
                                                );
                                                unset($clonedVideo->laravel_through_key);
                                                $clonedVideo->uploaded_by = $userId;
                                                $clonedVideo->uploader = $role;
                                                $clonedVideo->parent_id = $video->id;
                                                $clonedVideo->save();
                                            }
                                        }

                                        $clonedExercise->videos_pivot()->sync($clonedVideo->id);
                                    }
                                }
                            }
                            if(isset($superset['is_stag']) && $superset['is_stag'] && isset($superset['repsArray']) && is_array($superset['repsArray']))
                            {
                                    $values[] = [
                                        "workout_id" => $workout->id,
                                        "exercise_id" => $exerciseId,
                                        "id" => $superset['workout_exercise_id'] ?? null,
                                        "type" => $superset['type'] ?? null,
                                        "min" => $superset['min'] ?? null,
                                        "sec" => $superset['sec'] ?? null,
                                        "set" => $superset['set'] ?? null,
                                        "rep" => $superset['rep'] ?? null,
                                        "is_rest" => $superset['is_rest'] ?? 0,
                                        "rest_min" => $superset['rest_min'] ?? null,
                                        "rest_sec" => $superset['rest_sec'] ?? null,
                                        "is_stag" => 1,
                                        "stag" => $superset['stag'] ?? null,
                                        "stagger" => isset($superset['repsArray']) ? json_encode($superset['repsArray']) : null,
                                        'superset'=> $count,
                                        "sort" => $sort,
                                        "created_at" => Carbon::now(),
                                        "updated_at" => Carbon::now()
                                    ];

                                    $sort++;
                            }
                            else
                            {

                                $values[] = [
                                    "workout_id" => $workout->id,
                                    "exercise_id" => $exerciseId,
                                    "id" => $superset['workout_exercise_id'] ?? null,
                                    "type" => $superset['type'] ?? null,
                                    "min" => $superset['min'] ?? null,
                                    "sec" => $superset['sec'] ?? null,
                                    "set" => $superset['set'] ?? null,
                                    "rep" => $superset['rep'] ?? null,
                                    "is_rest" => $superset['is_rest'] ?? 0,
                                    "rest_min" => $superset['rest_min'] ?? null,
                                    "rest_sec" => $superset['rest_sec'] ?? null,
                                    "sort" => $sort,
                                    "is_stag" => 0,
                                    "stag" => null,
                                    "stagger" => null,
                                    'superset'=> $count,
                                    "created_at" => Carbon::now(),
                                    "updated_at" => Carbon::now()
                                ];
                                $sort++;
                            }
                        }
                        $count++;
                    }
                    else if(isset($exercise['is_stag']) && $exercise['is_stag'] && isset($exercise['repsArray']) && is_array($exercise['repsArray']))
                    {
                            $exerciseId = $exercise['id'] ?? null;
                            if($exerciseId)
                            {
                                $existingExercise = Exercise::find($exerciseId);
                                if(isset($existingExercise))
                                {
                                    $existingClonedExercise = Exercise::where('parent_id', $existingExercise->id)
                                    ->where('uploaded_by', $userId)
                                    ->where('uploader', $role)
                                    ->first();
                                    if(!isset($existingClonedExercise))
                                    {
                                        if ($existingExercise->uploaded_by == $userId && $existingExercise->uploader == $role) {
                                            $clonedExercise = $existingExercise;
                                        } else {
                                            $clonedExercise = $existingExercise->replicate()->fill(
                                                [
                                                    'title' => $existingExercise->title
                                                ]
                                            );
                                            $clonedExercise->uploaded_by = $userId;
                                            $clonedExercise->uploader = $role;
                                            $clonedExercise->parent_id = $existingExercise->id;
                                            $clonedExercise->save();
                                        }
                                    }
                                    else
                                    {
                                        $clonedExercise = $existingClonedExercise;
                                    }
                                    $exerciseId = $clonedExercise->id;

                                    $video = $existingExercise->video;
                                if($video)
                                {
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











                                            $clonedVideo = $video->replicate()->fill(
                                                [
                                                    'video_title' => $video->video_title
                                                ]
                                            );
                                            unset($clonedVideo->laravel_through_key);
                                            $clonedVideo->uploaded_by = $userId;
                                            $clonedVideo->uploader = $role;
                                            $clonedVideo->parent_id = $video->id;
                                            $clonedVideo->save();
                                        }
                                    }

                                    $clonedExercise->videos_pivot()->sync($clonedVideo->id);
                                }
                                }
                            }
                            $values[] = [
                                "workout_id" => $workout->id,
                                "exercise_id" => $exerciseId,
                                "id" => $exercise['workout_exercise_id'] ?? null,
                                "type" => $exercise['type'] ?? null,
                                "min" => $exercise['min'] ?? null,
                                "sec" => $exercise['sec'] ?? null,
                                "set" => $exercise['set'] ?? null,
                                "rep" => $exercise['rep'] ?? null,
                                "is_rest" => $exercise['is_rest'] ?? 0,
                                "rest_min" => $exercise['rest_min'] ?? null,
                                "rest_sec" => $exercise['rest_sec'] ?? null,
                                "is_stag" => 1,
                                "stag" => $exercise['stag'] ?? null,
                                "stagger" => isset($exercise['repsArray']) ? json_encode($exercise['repsArray']) : null,
                                'superset'=> null,
                                "sort" => $sort,
                                "created_at" => Carbon::now(),
                                "updated_at" => Carbon::now()
                            ];
                            $sort++;
                    }
                    else
                    {

                        $exerciseId = $exercise['id'] ?? null;
                        if($exerciseId)
                        {
                            $existingExercise = Exercise::find($exerciseId);
                            if(isset($existingExercise))
                            {
                                $existingClonedExercise = Exercise::where('parent_id', $existingExercise->id)
                                ->where('uploaded_by', $userId)
                                ->where('uploader', $role)
                                ->first();
                                if(!isset($existingClonedExercise))
                                {
                                    if ($existingExercise->uploaded_by == $userId && $existingExercise->uploader == $role) {
                                        $clonedExercise = $existingExercise;
                                    } else {
                                        $clonedExercise = $existingExercise->replicate()->fill(
                                            [
                                                'title' => $existingExercise->title
                                            ]
                                        );
                                        $clonedExercise->uploaded_by = $userId;
                                        $clonedExercise->uploader = $role;
                                        $clonedExercise->parent_id = $existingExercise->id;
                                        $clonedExercise->save();
                                    }
                                }
                                else
                                {
                                    $clonedExercise = $existingClonedExercise;
                                }
                                $exerciseId = $clonedExercise->id;

                                $video = $existingExercise->video;
                                    if($video)
                                    {
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











                                                $clonedVideo = $video->replicate()->fill(
                                                    [
                                                        'video_title' => $video->video_title
                                                    ]
                                                );
                                                unset($clonedVideo->laravel_through_key);
                                                $clonedVideo->uploaded_by = $userId;
                                                $clonedVideo->uploader = $role;
                                                $clonedVideo->parent_id = $video->id;
                                                $clonedVideo->save();
                                            }
                                        }

                                        $clonedExercise->videos_pivot()->sync($clonedVideo->id);
                                    }
                            }
                        }
                        $type = $exercise['type'] ?? null;
                        if(isset($type) && $type == "Duration" )
                        {
                            $set = null;
                            $rep = null;
                            $min = $exercise['min'];
                            $sec = $exercise['sec'];
                        }
                        if(isset($type) && $type == "Sets" )
                        {
                            $set = $exercise['set'];
                            $rep = $exercise['rep'];
                            $min = null;
                            $sec = null;
                        }
                        $values[] = [
                            "workout_id" => $workout->id,
                            "exercise_id" => $exerciseId,
                            "id" => $exercise['workout_exercise_id'] ?? null,
                            "type" => $type,
                            "min" => $min,
                            "sec" => $sec,
                            "set" => $set,
                            "rep" => $rep,
                            "is_rest" => $exercise['is_rest'] ?? 0,
                            "rest_min" => $exercise['rest_min'] ?? null,
                            "rest_sec" => $exercise['rest_sec'] ?? null,
                            "sort" => $sort,
                            "is_stag" => 0,
                            "stag" => null,
                            "stagger" => null,
                            "superset" => null,
                            "created_at" => Carbon::now(),
                            "updated_at" => Carbon::now()
                        ];
                        $sort++;
                    }
                }
                WorkoutExercise::upsert($values, ['id'], ['type','workout_id','exercise_id','min', 'sec', 'set', 'rep', 'is_rest', 'rest_min', 'rest_sec', 'sort', 'is_stag', 'stag','stagger', 'superset']);
            }
            $workout->visibility_type = $request->visibility_type ?? "Public";
            $workout->save();
            $workout->load('upload_by:id,first_name,last_name,profile_image','exercise.video');

            if ($onlyIsActive) {
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "workout" => $workout
                ];
                return response($response, $response["status"]);
            } else {
                $response = [
                    "status" => 200,
                    "message" => "Workout Updated Successfully",
                    "workout" => $workout
                ];
            }
            Helper::createActionLog($userId,$role,'workouts','update',$before_data,$workout);
        } else {
            $response = [
                "status" => 422,
                "message" => "Workout Not Found",
            ];
        }
        return response($response, $response["status"]);
    }

    public function getWorkouts(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $workouts = Workout::with('upload_by:id,first_name,last_name,profile_image','exercise.video')
        ->when($role == "Coach",function($query) use ($request,$userId,$role){
            $query->where(function($sq) use ($request,$userId,$role){
                $sq->where(function($subquery) use ($request,$userId,$role){
                    $subquery->where('uploaded_by',$userId)
                    ->where('uploader',$role);
                })->orWhere('visibility_type','Public');
            });
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
            ->whereNull('parent_id')
            ->latest();

        $response = Pagination::paginate($request, $workouts, "workouts");

        return response($response, $response["status"]);
    }

    public function getWorkout(Request $request, $id)
    {
        $workout = Workout::find($id);
        if (isset($workout)) {
            $workout->load([
                "upload_by:id,first_name,last_name,profile_image",
                "exercises" => function($query) {
                    $query->with('videos')
                        ->leftJoin('exercises', 'workout_exercises.exercise_id', 'exercises.id')
                        ->select(
                            'workout_exercises.*',
                            'workout_exercises.stagger as repsArray',
                            'workout_exercises.id as workout_exercise_id',
                            'exercises.id as id',
                            'exercises.title as title',
                            'exercises.tags as tags',
                            'exercises.description as description'
                        )->orderBy('workout_exercises.sort', 'asc');
                }
            ]);

            $exercises = $workout->exercises;
            unset($workout->exercises);
            $highestSort = $exercises->max('sort');
            $supersetArray = [];
            $processedStags = [];

            for ($i = 1; $i <= $highestSort; $i++) {
                $exercise = $exercises[$i - 1];
                if ($exercise->repsArray) {
                    $exercise->repsArray = json_decode($exercise->repsArray, true);
                }

                if (isset($exercise['superset'])) {
                    $supersetKey = 'superset' . $exercise['superset'];
                    if (!isset($supersetArray[$supersetKey])) {
                        $supersetArray[$supersetKey] = [];
                    }



                        $supersetArray[$supersetKey][] = $exercise->toArray();
                } else {


                        $supersetArray[] = $exercise->toArray();
                }

            }

            $finalArray = array_values($supersetArray);
            $workout->exercises = collect($finalArray);

            $response = [
                "status" => 200,
                "message" => "Workout Fetched Successfully",
                "workout" => $workout
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Workout Not Found!"
            ];
        }

        return response($response, $response["status"]);
    }
    public function deleteWorkout(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $workout = Workout::find($id);
        if (isset($workout)) {
            UserCompletedWorkout::where('workout_id',$workout->id)->delete();
            PlanWorkout::where('workout_id',$workout->id)->delete();
            Helper::createActionLog($userId,$role,'workouts','delete',$workout,null);
            $workout->delete();
            $response = [
                "status" => 200,
                "message" => "Workout Deleted Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Workout Not Found!",
            ];
        }

        return response($response, $response["status"]);
    }

    public function programWeeklyWorkouts(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'week_start_date' => 'required|date',
                'workouts' => 'required|array',
                'workouts.*.workout_id' => 'required|exists:workouts,id',
                'workouts.*.scheduled_date' => 'required|date',
                'workouts.*.scheduled_time' => 'nullable|date_format:H:i'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $role = $request->role;
            $userId = Auth::guard(strtolower($role))->id();

            DB::beginTransaction();

            foreach ($request->workouts as $workoutData) {
                WorkoutCalendar::updateOrCreate(
                    [
                        'user_id' => $request->user_id,
                        'workout_id' => $workoutData['workout_id'],
                        'scheduled_date' => $workoutData['scheduled_date']
                    ],
                    [
                        'scheduled_time' => $workoutData['scheduled_time'] ?? null,
                        'status' => 'scheduled'
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Weekly workouts programmed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to program weekly workouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getClientWorkoutCalendar($userId, $date)
    {
        try {
            $workouts = WorkoutCalendar::with(['workout', 'user'])
                ->where('user_id', $userId)
                ->whereDate('scheduled_date', $date)
                ->orderBy('scheduled_time')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Client workout calendar retrieved successfully',
                'data' => $workouts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve client workout calendar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addWorkoutEquipment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'workout_id' => 'required|exists:workouts,id',
                'equipment' => 'required|array',
                'equipment.*.name' => 'required|string',
                'equipment.*.icon' => 'nullable|string',
                'equipment.*.description' => 'nullable|string',
                'equipment.*.is_required' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            WorkoutEquipment::where('workout_id', $request->workout_id)->delete();

            foreach ($request->equipment as $equipmentData) {
                WorkoutEquipment::create([
                    'workout_id' => $request->workout_id,
                    'equipment_name' => $equipmentData['name'],
                    'equipment_icon' => $equipmentData['icon'] ?? null,
                    'equipment_description' => $equipmentData['description'] ?? null,
                    'is_required' => $equipmentData['is_required'] ?? true
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Workout equipment added successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to add workout equipment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addExerciseWarmUp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'exercise_id' => 'required|exists:exercises,id',
                'warmup_exercises' => 'required|array',
                'warmup_exercises.*.name' => 'required|string',
                'warmup_exercises.*.description' => 'nullable|string',
                'warmup_exercises.*.thumbnail' => 'nullable|string',
                'warmup_exercises.*.duration' => 'nullable|string',
                'warmup_exercises.*.reps' => 'nullable|integer',
                'warmup_exercises.*.order' => 'integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            ExerciseWarmup::where('exercise_id', $request->exercise_id)->delete();

            foreach ($request->warmup_exercises as $index => $warmupData) {
                ExerciseWarmup::create([
                    'exercise_id' => $request->exercise_id,
                    'name' => $warmupData['name'],
                    'description' => $warmupData['description'] ?? null,
                    'thumbnail' => $warmupData['thumbnail'] ?? null,
                    'duration' => $warmupData['duration'] ?? null,
                    'reps' => $warmupData['reps'] ?? null,
                    'order' => $warmupData['order'] ?? $index
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Exercise warm-up added successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to add exercise warm-up',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addExerciseCoolDown(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'exercise_id' => 'required|exists:exercises,id',
                'cooldown_exercises' => 'required|array',
                'cooldown_exercises.*.name' => 'required|string',
                'cooldown_exercises.*.description' => 'nullable|string',
                'cooldown_exercises.*.thumbnail' => 'nullable|string',
                'cooldown_exercises.*.duration' => 'nullable|string',
                'cooldown_exercises.*.reps' => 'nullable|integer',
                'cooldown_exercises.*.order' => 'integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            ExerciseCooldown::where('exercise_id', $request->exercise_id)->delete();

            foreach ($request->cooldown_exercises as $index => $cooldownData) {
                ExerciseCooldown::create([
                    'exercise_id' => $request->exercise_id,
                    'name' => $cooldownData['name'],
                    'description' => $cooldownData['description'] ?? null,
                    'thumbnail' => $cooldownData['thumbnail'] ?? null,
                    'duration' => $cooldownData['duration'] ?? null,
                    'reps' => $cooldownData['reps'] ?? null,
                    'order' => $cooldownData['order'] ?? $index
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Exercise cool-down added successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to add exercise cool-down',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEquipmentList()
    {
        try {
            $equipment = [
                ['name' => 'Dumbbells', 'icon' => 'fitness-outline', 'description' => 'Adjustable weight dumbbells'],
                ['name' => 'Barbell', 'icon' => 'barbell-outline', 'description' => 'Olympic barbell with plates'],
                ['name' => 'Kettlebell', 'icon' => 'fitness-outline', 'description' => 'Cast iron kettlebell'],
                ['name' => 'Resistance Bands', 'icon' => 'fitness-outline', 'description' => 'Elastic resistance bands'],
                ['name' => 'Yoga Mat', 'icon' => 'body-outline', 'description' => 'Non-slip yoga/exercise mat'],
                ['name' => 'Bench', 'icon' => 'fitness-outline', 'description' => 'Adjustable workout bench'],
                ['name' => 'Pull-up Bar', 'icon' => 'fitness-outline', 'description' => 'Doorway or wall-mounted pull-up bar'],
                ['name' => 'Bodyweight', 'icon' => 'body-outline', 'description' => 'No equipment needed']
            ];

            return response()->json([
                'status' => true,
                'message' => 'Equipment list retrieved successfully',
                'data' => $equipment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving equipment list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate AI workout
     */
    public function generateAIWorkout(Request $request)
    {
        $request->validate([
            'fitness_level' => 'required|in:beginner,intermediate,advanced',
            'duration' => 'required|integer|min:15|max:120',
            'workout_type' => 'required|in:strength,cardio,flexibility,hiit,yoga',
            'target_areas' => 'required|array',
            'equipment' => 'required|array',
            'goals' => 'string|nullable'
        ]);

        try {
            $aiService = new \App\Services\WorkoutAIService();
            $parameters = $request->all();
            $parameters['created_by'] = auth()->id();

            $result = $aiService->generateWorkout($parameters);

            if ($result['success']) {
                return response()->json([
                    'status' => true,
                    'message' => 'AI workout generated successfully',
                    'data' => $result['workout']
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => $result['message']
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error generating AI workout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI workout suggestions for a user
     */
    public function getAIWorkoutSuggestions(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'limit' => 'integer|min:1|max:10'
        ]);

        try {
            $aiService = new \App\Services\WorkoutAIService();
            $result = $aiService->generateWorkoutSuggestions(
                $request->user_id,
                $request->limit ?? 5
            );

            if ($result['success']) {
                return response()->json([
                    'status' => true,
                    'message' => 'AI workout suggestions generated successfully',
                    'data' => $result['suggestions']
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => $result['message']
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error generating AI suggestions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getExercisesDropDown()
    {
        try {
            $exercises = Exercise::select('id', 'title as name')->get();
            return response()->json([
                'status' => 200,
                'exercises' => $exercises
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to get exercises: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getVideoLibrary()
    {
        return response()->json([
            'status' => 200,
            'videos' => [],
            'message' => 'Video library coming soon'
        ], 200);
    }

    public function getVideoCategories()
    {
        return response()->json([
            'status' => 200,
            'categories' => ['Warm-up', 'Strength', 'Cardio', 'Cool-down'],
            'message' => 'Video categories'
        ], 200);
    }

    public function getTemplates()
    {
        return response()->json([
            'status' => 200,
            'templates' => [],
            'message' => 'Workout templates coming soon'
        ], 200);
    }

    public function getQuickTemplates()
    {
        return response()->json([
            'status' => 200,
            'templates' => [],
            'message' => 'Quick templates coming soon'
        ], 200);
    }

    public function getRecentWorkouts()
    {
        return response()->json([
            'status' => 200,
            'workouts' => [],
            'message' => 'Recent workouts'
        ], 200);
    }

    public function getAnalyticsOverview()
    {
        return response()->json([
            'status' => 200,
            'overview' => [
                'total_workouts' => 0,
                'total_videos' => 0,
                'total_templates' => 0,
                'engagement_rate' => 0
            ]
        ], 200);
    }

    public function getVideoPopularity()
    {
        return response()->json([
            'status' => 200,
            'videos' => []
        ], 200);
    }

    public function getTemplateSuccess()
    {
        return response()->json([
            'status' => 200,
            'templates' => []
        ], 200);
    }

    public function getClientEngagement()
    {
        return response()->json([
            'status' => 200,
            'engagement' => []
        ], 200);
    }

    public function getWeeklyPlans()
    {
        return response()->json([
            'status' => 200,
            'plans' => []
        ], 200);
    }

    /**
     * Get workout types dropdown
     */
    public function getWorkoutTypesDropdown(Request $request)
    {
        $workoutTypes = [
            ['id' => 1, 'name' => 'Strength Training'],
            ['id' => 2, 'name' => 'Cardio'],
            ['id' => 3, 'name' => 'HIIT'],
            ['id' => 4, 'name' => 'Yoga'],
            ['id' => 5, 'name' => 'CrossFit'],
            ['id' => 6, 'name' => 'Flexibility'],
            ['id' => 7, 'name' => 'Endurance'],
            ['id' => 8, 'name' => 'AMRAP'],
            ['id' => 9, 'name' => 'EMOM'],
            ['id' => 10, 'name' => 'RFT'],
        ];

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Workout Types Retrieved Successfully',
            'data' => $workoutTypes
        ]);
    }

    /**
     * Get equipment dropdown
     */
    public function getEquipmentDropdown(Request $request)
    {
        // Call existing method if available
        if (method_exists($this, 'getEquipmentList')) {
            return $this->getEquipmentList($request);
        }

        $equipment = [
            ['id' => 1, 'name' => 'Dumbbells'],
            ['id' => 2, 'name' => 'Barbell'],
            ['id' => 3, 'name' => 'Kettlebell'],
            ['id' => 4, 'name' => 'Resistance Bands'],
            ['id' => 5, 'name' => 'Pull-up Bar'],
            ['id' => 6, 'name' => 'Bench'],
            ['id' => 7, 'name' => 'Treadmill'],
            ['id' => 8, 'name' => 'Rowing Machine'],
            ['id' => 9, 'name' => 'Bike'],
            ['id' => 10, 'name' => 'None (Bodyweight)'],
        ];

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Equipment Dropdown Retrieved Successfully',
            'data' => $equipment
        ]);
    }
}
