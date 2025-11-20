<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Http\Resources\PlanWorkoutResource;
use App\Http\Resources\WorkoutResource;
use App\Models\AppNotification;
use App\Models\BodyPoint;
use App\Models\Exercise;
use App\Models\ExerciseCooldown;
use App\Models\ExerciseWarmup;
use App\Models\Plan;
use App\Models\PlanWorkout;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\UserCompletedWorkout;
use App\Models\Workout;
use App\Models\WorkoutCalendar;
use App\Models\WorkoutEquipment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WorkoutController extends Controller
{
    public function getMyPlans(Request $request)
    {
        $user = $request->user();
        $currentDate = Carbon::now()->toDateString();

        $reportedWorkoutContents = Helper::reportedContents($user,"Workout") ?? [];
        $plan = Plan::with([
            'users' => function ($query) use ($user, $currentDate) {
                $query->where('users.id', $user->id)
                    ->whereDate('assign_plans.start_date', '<=', $currentDate)
                    ->whereDate('assign_plans.end_date', '>=', $currentDate)
                    ->select(['users.*', 'assign_plans.start_date as start_date', 'assign_plans.end_date as end_date']);
            },
            'organizations' => function ($query) use ($user, $currentDate) {
                $query->whereDate('assign_plans.start_date', '<=', $currentDate)
                    ->whereDate('assign_plans.end_date', '>=', $currentDate)
                    ->whereHas('employees', function ($subquery) use ($user) {
                        $subquery->where('id', $user->id);
                    })
                    ->select(['organizations.*', 'assign_plans.start_date as start_date', 'assign_plans.end_date as end_date']);
            },
            'workouts'
        ])->withCount('workouts','user_workouts')
        ->whereHas('users', function ($query) use ($user,$currentDate) {
            $query->where('users.id', $user->id)
            ->whereDate('assign_plans.start_date', '<=', $currentDate)
            ->whereDate('assign_plans.end_date', '>=', $currentDate);
        })->orWhereHas('organizations', function ($query) use ($user,$currentDate) {
            $query->whereDate('assign_plans.start_date', '<=', $currentDate)
            ->whereDate('assign_plans.end_date', '>=', $currentDate)
            ->whereHas('employees', function ($subquery) use ($user) {
                $subquery->where('id', $user->id);
            });
        })->where('is_active', 1)->first();

        $body_points = Helper::getBodyPoints($user);

        $workouts = [];

        if (isset($plan)) {
            $day = $request->query('day');
            $week = $request->query('week');
            $phase = $request->query('phase');
            if (isset($plan)) {
                if ($plan->type === "Program") {

                    $phases = [];
                    $totalPhases = $plan->workouts->max('phase');

                    for ($i = 1; $i <= $totalPhases; $i++) {
                        $phase = [
                            'phase' => $i,
                            'weeks' => [],
                            'total_days' => 0
                        ];

                        $totalWeeks = $plan->workouts->where('phase', $i)->max('week');
                        for ($j = 1; $j <= $totalWeeks; $j++) {
                            $week = [
                                'week' => $j,
                                'days' => [],
                                'total_days' => $plan->workouts->where('phase', $i)->where('week', $j)->max('day')
                            ];

                            $phase['weeks'][] = $week;
                            $phase['total_days'] += $week['total_days'];
                        }

                        $phases[] = $phase;
                    }

                    $startDate = $plan->users->first()?->start_date ?? $plan->organizations->first()?->start_date;

                    if ($startDate) {
                        $startDate = Carbon::parse($startDate);

                        $daysElapsed = $startDate->diffInDays($currentDate);

                        $currentWeek = $startDate->isSameDay($currentDate) ? 1 : max(1, $startDate->diffInWeeks($currentDate) + 1);
                        $currentDayOfWeek = $startDate->isSameDay($currentDate) ? 1 : max(1, ($startDate->diffInDays($currentDate) % 7) + 1);


                        $currentPhase = Helper::getCurrentPhase($currentWeek, $phases);
                        $currentPhaseDetails = $phases[$currentPhase - 1] ?? null;

                        if ($currentPhaseDetails) {
                            $weeksBeforeCurrentPhase = array_sum(array_map(fn($p) => count($p['weeks']), array_slice($phases, 0, $currentPhase - 1)));
                            $currentWeekInPhase = $currentWeek - $weeksBeforeCurrentPhase;
                            $sumOfWeeksInPhase = count($currentPhaseDetails['weeks']);
                            $plan['current_phase_weeks'] = $sumOfWeeksInPhase;
                        } else {
                            $currentWeekInPhase = 1;
                            $plan['current_phase_weeks'] = 1;
                        }

                        $day = $request->query('day', $currentDayOfWeek);
                        $week = $request->query('week', $currentWeekInPhase);
                        $phase = $request->query('phase', $currentPhase);

                        $plan['current_week'] = $currentWeek;
                        $plan['current_day'] = $currentDayOfWeek;
                        $plan['current_phase'] = $currentPhase;
                        $plan['current_week_in_phase'] = $currentWeekInPhase;

                        $this->updateIsRestDay($plan,$user,$currentPhase,$currentWeekInPhase,$currentDayOfWeek);
                    }

                } else {
                    $day = $request->query('day');
                    $week = $request->query('week');
                    $phase = $request->query('phase');

                    $plan['current_week'] = null;
                    $plan['current_day'] = null;
                    $plan['current_phase'] = null;
                    $plan['current_week_in_phase'] = null;
                }

                unset($plan->workouts, $plan->users, $plan->organizations);

                $workouts = PlanWorkout::with([
                    'plan' => function ($subquery) use ($user) {
                        $subquery->withCount(['user_workouts' => function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        }])->where('is_active', 1);
                    },
                    'workout' => function ($subquery) use ($user,$reportedWorkoutContents) {
                        $subquery->select('id', 'title')
                            ->with('exercise:id,exercise_id,workout_id', 'exercise.video')
                            ->withCount('exercises')
                            ->with(['user_exercises' => function ($query) use ($user) {
                                $query->where('user_id', $user->id);
                            }]);
                    },
                    'user_workout' => function ($subquery) use ($user) {
                        $subquery->where('user_id', $user->id)
                            ->select('plan_workout_id', 'start_time', 'end_time', 'status');
                    }
                ])
                ->where(function ($query) use ($reportedWorkoutContents) {
                    $query->where('is_rest', 1)
                          ->orWhere(function ($subQuery) use ($reportedWorkoutContents) {
                              $subQuery->where('is_rest', 0)
                                       ->when(!empty($reportedWorkoutContents), function ($nestedQuery) use ($reportedWorkoutContents) {
                                           $nestedQuery->whereHas('workout', function ($workoutQuery) use ($reportedWorkoutContents) {
                                               $workoutQuery->whereNotIn('id', $reportedWorkoutContents);
                                           });
                                       });
                          });
                })->whereHas('plan', function ($query) use ($plan) {
                        $query->where('id', $plan->id)
                            ->where('is_active', 1);
                    })
                    ->when($request->filled('phase'), function ($query) use ($phase) {
                        $query->where('phase', $phase);
                    })
                    ->when($request->filled('search'), function ($query) use ($request) {
                        $query->whereHas('workout', function ($subquery) use ($request) {
                            $subquery->where('title', 'LIKE', '%' . $request->search . '%');
                        });
                    })
                    ->when($request->filled('week'), function ($query) use ($week) {
                        $query->where('week', $week);
                    })
                    ->when($request->filled('filter'), function ($query) use ($request) {
                        $filters = $request->query('filter');
                        $query->whereHas('workout', function ($subquery) use ($filters) {
                            $subquery->whereHas('exercises', function ($query) use ($filters) {
                                $query->whereHas('exercise', function ($subquery) use ($filters) {
                                    $subquery->where(function ($innerQuery) use ($filters) {
                                        foreach ($filters as $filter) {
                                            $innerQuery->orWhereJsonContains('tags', $filter);
                                        }
                                    });
                                });
                            });
                        });
                    })
                    ->when($request->filled('day'), function ($query) use ($day) {
                        $query->where('day', $day);
                    })
                    ->when(isset($phase, $day, $week), function ($query) use ($phase, $week, $day) {
                        $query->where('phase', $phase)
                            ->where('week', $week)
                            ->where('day', $day);
                    })
                    ->orderBy('sort', 'asc');

                $response = Pagination::paginate($request, $workouts, 'workouts');
                $response['workouts'] = PlanWorkoutResource::collection($response['workouts']);

                $plan['total_page'] = $response['total_page'];
                $plan['per_page'] = $response['per_page'];
                $plan['page'] = $response['page'];
                $plan['total_records'] = $response['total_records'];
                $plan['workouts'] = $response['workouts'];
                $plan['total_phases'] = $plan->totalData($plan->id)['total_phases'] ?? null;
                $plan['total_weeks'] = $plan->totalData($plan->id)['total_weeks'] ?? null;
                $plan['total_days'] = $plan->totalData($plan->id)['total_days'] ?? null;
                $plan['body_points'] = $body_points * ($plan->workouts_count ?? 0);

                return [
                    'status' => 200,
                    'message' => 'Plan Fetched Successfully',
                    'plan' => $plan
                ];
            }
            else
            {
                $day = $request->query('day');
                $week = $request->query('week');
                $phase = $request->query('phase');
                $plan['current_week'] = null;
                $plan['current_day'] = null;
                $plan['current_phase'] = null;
                $plan['current_week_in_phase'] = null;
            }
            unset($plan->workouts);
            unset($plan->users);
            unset($plan->organizations);
            $workouts = PlanWorkout::with([
                'plan' => function ($subquery) use ($user){
                    $subquery->withCount(['user_workouts'=> function($query) use ($user){
                        $query->where('user_id',$user->id);
                    }])->where('is_active',1);
                },
                'workout' => function ($subquery) use ($user){
                    $subquery->select('id', 'title')
                        ->with('exercise:id,exercise_id,workout_id', 'exercise.video')
                        ->withCount('exercises')
                        ->with(['user_exercises' => function($query) use ($user){
                            $query->where('user_id',$user->id);
                        }]);
                },
                'user_workout' => function ($subquery) use ($user) {
                    $subquery->where('user_id', $user->id)
                        ->select('plan_workout_id', 'start_time', 'end_time', 'status');
                }
            ])
                ->whereHas('plan', function ($query) use ($plan) {
                    $query->where('id', $plan->id)
                    ->where('is_active',1);
                })
                ->when($request->filled('phase'), function ($query) use ($phase,$plan) {
                    $query->where('phase', $phase);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->whereHas('workout',function($subquery) use ($request){
                        $subquery->where('title', 'LIKE', '%' . $request->search . '%');
                    });
                })
                ->when($request->filled('week'), function ($query) use ($week,$plan) {
                    $query->where('week', $week);
                })
                ->when($request->filled('filter'), function ($query) use ($request) {
                    $filters = $request->query('filter');
                    $query->whereHas('workout', function ($subquery) use ($filters) {
                        $subquery->whereHas('exercises', function ($query) use ($filters) {
                            $query->whereHas('exercise', function ($subquery) use ($filters) {
                                $subquery->where(function ($innerQuery) use ($filters) {
                                    foreach ($filters as $filter) {
                                        $innerQuery->orWhereJsonContains('tags', $filter);
                                    }
                                });
                            });
                        });
                    });
                })
                ->when($request->filled('day'), function ($query) use ($day,$plan) {
                    $query->where('day', $day);
                })
                ->when(isset($phase) && isset($day) && isset($week),function ($query) use ($phase,$week,$day){
                    $query->where('phase',$phase)
                    ->where('day',$day)
                    ->where('week',$week);
                })
                ->orderBy('sort', 'asc');

            $response = Pagination::paginate($request, $workouts, "workouts");
            $response['workouts'] = PlanWorkoutResource::collection($response['workouts']);

            $plan['total_page'] = $response['total_page'];
            $plan['per_page'] = $response['per_page'];
            $plan['page'] = $response['page'];
            $plan['total_records'] = $response['total_records'];
            $plan['workouts'] = $response['workouts'];
            $plan['total_phases'] = $plan->totalData($plan->id)['total_phases'] ?? null;
            $plan['total_weeks'] = $plan->totalData($plan->id)['total_weeks'] ?? null;
            $plan['total_days'] = $plan->totalData($plan->id)['total_days'] ?? null;
            $plan['body_points'] = $body_points * $plan->workouts_count ?? 0;

            $response = [
                "status" => 200,
                "message" => "Plan Fetched Successfully",
                "plan" => $plan
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "No Plan Assigned!",
            ];
        }



        return response($response, $response["status"]);
    }






    public function getWorkout(Request $request, $id)
    {
        $user = $request->user();
        $reportedExerciseContents = Helper::reportedContents($user,"Exercise") ?? [];
        $reportedVideoContents = Helper::reportedContents($user,"Video") ?? [];
        $workout = Workout::with([
            "upload_by:id,first_name,last_name,profile_image",
            "exercises" => function($query) use ($reportedExerciseContents,$reportedVideoContents) {
                $query->where(function($query) use ($reportedExerciseContents){
                    $query->where('is_rest',1)
                        ->orWhere(function($query) use ($reportedExerciseContents){
                            $query->where('is_rest',0)
                            ->when(!empty($reportedExerciseContents),function($query) use ($reportedExerciseContents){
                                $query->whereHas('exercise',function($subquery) use ($reportedExerciseContents){
                                    $subquery->whereNotIn('id',$reportedExerciseContents);
                                });
                        });
                    });
                })
                ->with(['videos' => function ($query) use ($reportedVideoContents)
                {
                    $query->when(!empty($reportedVideoContents),function($subquery) use ($reportedVideoContents){
                        $subquery->whereNotIn('id',$reportedVideoContents);
                    });
                }])
                    ->leftJoin('exercises', 'workout_exercises.exercise_id', 'exercises.id')
                    ->select(
                        'workout_exercises.*',
                        'workout_exercises.id as workout_exercise_id',
                        'exercises.id as id',
                        'exercises.title as title',
                        'exercises.tags as tags',
                        'exercises.description as description'
                    )->orderBy('workout_exercises.sort', 'asc');
            },
            'user_exercises'  => function ($subquery) use ($user,$request) {
                $subquery->where('user_id', $user->id);
            }
        ])
        ->whereHas('plans',function($query) use ($request){
            $query->where('id',$request->query('plan_workout_id'))
            ->where('is_active',1);
        })
        ->withCount('exercises')->withCount(['exercises as exercise_count' => function ($query) {
            $query->where('is_rest', 0);
        }])->withCount(['exercises as rest_count' => function ($query) {
            $query->where('is_rest', 1);
        }])->where('is_active',1)->find($id);

        if (isset($workout)) {
            $body_points = Helper::getBodyPoints($user);
            $workout['body_points'] = $body_points;
            $workout['plan_workout_id'] = $request->query('plan_workout_id');
            $workout = new WorkoutResource($workout);

            $response = [
                'status' => 200,
                'message' => 'Workout Fetched Successfully',
                'workout' => $workout,
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Workout Not Found!"
            ];
        }

        return response($response, $response["status"]);
    }

    public function getExercise(Request $request, $id)
    {
        $exercise = Exercise::with('videos')->find($id);
        if (isset($exercise)) {
            $response = [
                "status" => 200,
                "message" => "Exercise Fetched Successfully",
                "exercise" => $exercise
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Exercise Not Found!"
            ];
        }

        return response($response, $response["status"]);
    }

    public function updateWorkoutExerciseStatus(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required',
            'plan_workout_id' => 'required',
            'status' => 'required|in:Completed,In Progress',
        ]);

        if ($validator->fails()) {
            return response([
                'status' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $exercise_id = $request->input('exercise_id') ?? null;
        $workout_id = $request->input('workout_id') ?? null;
        $workout_exercise_id = $request->input('workout_exercise_id') ?? null;

        $completedWorkout = UserCompletedWorkout::updateOrCreate(
            [
                'user_id' => $user->id,
                'plan_id' => $request->input('plan_id'),
                'plan_workout_id' => $request->input('plan_workout_id'),
                'workout_id' => $workout_id,
                'workout_exercise_id' => $workout_exercise_id,
                'exercise_id' => $exercise_id,
            ],
            [
                'status' => $request->input('status'),
            ]
        );

        if ($completedWorkout->wasRecentlyCreated) {
            $completedWorkout->start_time = Carbon::now();
            $completedWorkout->save();
        }

        if($request->status == "Completed")
        {
            $completedWorkout->end_time = Carbon::now();
            $completedWorkout->save();
        }

        $workout = Workout::withCount(['exercises', 'user_exercises' => function ($query) use ($user) {
            $query->where('user_id', $user->id)->where('status', 'Completed');
        }])->find($request->input('workout_id'));

        $body_points = Helper::getBodyPoints($user);

        if ($workout && $workout->exercises_count === $workout->user_exercises_count) {
            $user->increment('body_points', $body_points);

            Transaction::create([
                "user_id" => $user->id,
                "type" => "Earned",
                "transaction_type" => Transaction::Body_Points,
                "transaction_date" => Carbon::now()->toDateString(),
                "name" => "Workout Completed",
                "description" => "You have earned $body_points Body Points.",
                "points" => $body_points,
            ]);
        }

        $response = [
            'status' => 200,
            'message' => 'Workout exercise status updated successfully.',
        ];

        return response($response, $response['status']);
    }

    public function getTags(Request $request)
    {
        $limit = $request->query('limit', 10);
        $type = $request->query('type', 'Video');
        $user = $request->user();
        $currentDate = Carbon::now()->toDateString();

        $plan = Plan::whereHas('users', function ($query) use ($user, $currentDate) {
                $query->where('users.id', $user->id)
                    ->whereDate('assign_plans.start_date', '<=', $currentDate)
                    ->whereDate('assign_plans.end_date', '>=', $currentDate);
            })->orWhereHas('organizations', function ($query) use ($user, $currentDate) {
                $query->whereDate('assign_plans.start_date', '<=', $currentDate)
                    ->whereDate('assign_plans.end_date', '>=', $currentDate)
                    ->whereHas('employees', function ($subquery) use ($user) {
                        $subquery->where('id', $user->id);
                    });
            })->where('is_active', 1)->first();

        if (isset($plan)) {
            $workouts = PlanWorkout::with(['workout' => function ($subquery) use ($user) {
                $subquery->select('id', 'title')
                    ->withCount('exercises')
                    ->with(['exercises' => function ($exerciseQuery) {
                        $exerciseQuery->where('is_rest', 0)
                            ->with('exercise');
                    }]);
            }])->whereHas('plan', function ($query) use ($plan) {
                $query->where('id', $plan->id)
                    ->where('is_active', 1);
            })->where('is_rest', 0)->get();

            $tags = [];

            foreach ($workouts as $workout) {
                foreach ($workout->workout->exercises as $exercise) {
                    foreach ($exercise->exercise->tags as $tag) {
                        $tags[] = ['tag' => $tag];
                    }
                }
            }

            $tags = array_values(array_unique($tags, SORT_REGULAR));

            $tags = array_map(function ($tag, $index) {
                return [
                    'id' => $index + 1,
                    'tag' => $tag['tag']
                ];
            }, $tags, array_keys($tags));
            $response = [
                "status" => 200,
                "message" => "Tags Fetched Successfully",
                "tags" => $tags,
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "No Plan Assigned",
            ];
        }

        return response($response, $response["status"]);
    }

    public function getWorkoutStatus(Request $request)
    {
        $user = $request->user();
        $currentDate = Carbon::now()->toDateString();

        $plan = Plan::whereHas('users', function ($query) use ($user, $currentDate) {
            $query->where('users.id', $user->id)
                ->whereDate('assign_plans.start_date', '<=', $currentDate)
                ->whereDate('assign_plans.end_date', '>=', $currentDate);
        })->orWhereHas('organizations', function ($query) use ($user, $currentDate) {
            $query->whereDate('assign_plans.start_date', '<=', $currentDate)
                ->whereDate('assign_plans.end_date', '>=', $currentDate)
                ->whereHas('employees', function ($subquery) use ($user) {
                    $subquery->where('id', $user->id);
                });
        })->where('is_active', 1)->first();

        if (isset($plan)) {
            $workouts = PlanWorkout::with(['workout' => function ($subquery) use ($user) {
                $subquery->select('id', 'title')
                    ->withCount('exercises')
                    ->with(['user_exercises' => function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    }]);
            },
            'user_workout' => function ($subquery) use ($user) {
                $subquery->where('user_id', $user->id)
                    ->select('plan_workout_id', 'start_time', 'end_time', 'status');
            }])->whereHas('plan', function ($query) use ($plan) {
                    $query->where('id', $plan->id)
                        ->where('is_active', 1);
                })
                ->get();

            $workoutIds = $workouts->pluck('id');

            if (empty($workouts)) {
                $phaseStatus = 'Not Started';
                $weekStatus = 'Not Started';
                $dayStatus = 'Not Started';
                $response = [
                    "status" => 200,
                    "message" => "Workout Status Fetched",
                    "workout_status" => [
                        "phase" => $phaseStatus,
                        "week" => $weekStatus,
                        "day" => $dayStatus
                    ]
                ];
                return response($response, $response['status']);
            }

            $phaseStatus = 'Not Started';
            $weekStatus = 'Not Started';
            $dayStatus = 'Not Started';

            if ($request->filled('phase')) {
                $phaseWorkouts = $workouts->where('phase', $request->query('phase'));
                $totalExercises = 0;
                $totalUserExercises = 0;
                foreach($phaseWorkouts as $workout)
                {
                    $plan_workout = $workout ?? null;
                    $workout = $plan_workout->workout ?? null;
                    $user_exercises = $workout ? $workout->user_exercises : 0;
                    $exercises_count = $workout ? $workout->exercises_count : 0;
                    $user_exercises_count = $user_exercises ? $user_exercises->where('plan_workout_id',$plan_workout->id)->where('user_id',$user->id)->where('status',"Completed")->count() : 0;
                        if($exercises_count == $user_exercises_count)
                    {
                        $status = "Completed";
                    }
                    else if ($user_exercises_count < $exercises_count && $user_exercises_count != 0)
                    {
                        $status = "In Progress";
                    }
                    else
                    {
                        $status = "Not Started";
                    }

                    $totalExercises = $totalExercises + $exercises_count;
                    $totalUserExercises = $totalUserExercises + $user_exercises_count;
                }
                if ($totalUserExercises == 0) {
                    $phaseStatus = 'Not Started';
                } elseif ($totalUserExercises > 0 && $totalUserExercises < $totalExercises) {
                    $phaseStatus = 'In Progress';
                } elseif ($totalUserExercises == $totalExercises) {
                    $phaseStatus = 'Completed';
                }
            }

            if ($request->filled('week')) {
                $weekWorkouts = $workouts->where('phase',$request->query('phase'))->where('week', $request->query('week'));
                $totalExercises = 0;
                $totalUserExercises = 0;

                foreach($weekWorkouts as $workout)
                {
                    $plan_workout = $workout ?? null;
                    $workout = $plan_workout->workout ?? null;
                    $user_exercises = $workout ? $workout->user_exercises : 0;
                    $exercises_count = $workout ? $workout->exercises_count : 0;
                    $user_exercises_count = $user_exercises ? $user_exercises->where('plan_workout_id',$plan_workout->id)->where('user_id',$user->id)->where('status',"Completed")->count() : 0;
                         if($exercises_count == $user_exercises_count)
                    {
                        $status = "Completed";
                    }
                    else if ($user_exercises_count < $exercises_count && $user_exercises_count != 0)
                    {
                        $status = "In Progress";
                    }
                    else
                    {
                        $status = "Not Started";
                    }

                    $totalExercises = $totalExercises + $exercises_count;
                    $totalUserExercises = $totalUserExercises + $user_exercises_count;


                }
                if ($totalUserExercises == 0) {
                    $weekStatus = 'Not Started';
                } elseif ($totalUserExercises > 0 && $totalUserExercises < $totalExercises) {
                    $weekStatus = 'In Progress';
                } elseif ($totalUserExercises == $totalExercises) {
                    $weekStatus = 'Completed';
                }
            }

            if ($request->filled('day')) {
                $dayWorkouts = $workouts->where('phase',$request->query('phase'))->where('week',$request->query('week'))->where('day', $request->query('day'));
                $totalExercises = 0;
                $totalUserExercises = 0;
                if($dayWorkouts->isEmpty())
                {
                    $response = [
                        "status" => 200,
                        "message" => "Workout Status Fetched",
                        "workout_status" => [
                            "phase" => $phaseStatus,
                            "week" => $weekStatus,
                            "day" => $dayStatus
                        ]
                    ];
                    return response($response, $response['status']);
                }
                foreach($dayWorkouts as $workout)
                {
                    $plan_workout = $workout ?? null;
                    $workout = $plan_workout->workout ?? null;
                    $user_exercises = $workout ? $workout->user_exercises : 0;
                    if(!$workout)
                    {
                        $exercises_count = 1;
                        $user_exercises_count = $plan_workout->user_workout ? $plan_workout->user_workout->where('plan_workout_id',$plan_workout->id)->where('user_id',$user->id)->where('status',"Completed")->count() : 0;
                    }
                    else
                    {
                        $exercises_count = $workout ? $workout->exercises_count : 0;
                        $user_exercises_count = $user_exercises ? $user_exercises->where('plan_workout_id', $plan_workout->id)->count() : 0;
                    }


                    if($exercises_count == $user_exercises_count)
                    {
                        $status = "Completed";
                    }
                    else if ($user_exercises_count < $exercises_count && $user_exercises_count != 0)
                    {
                        $status = "In Progress";
                    }
                    else
                    {
                        $status = "Not Started";
                    }

                    $totalExercises = $totalExercises + $exercises_count;
                    $totalUserExercises = $totalUserExercises + $user_exercises_count;

                }

                if ($totalUserExercises == $totalExercises) {
                    $dayStatus = 'Completed';
                } elseif ($totalUserExercises > 0 && $totalUserExercises < $totalExercises) {
                    $dayStatus = 'In Progress';
                } elseif ($totalUserExercises == 0) {
                    $dayStatus = 'Not Started';
                }
            }

            $response = [
                "status" => 200,
                "message" => "Workout Status Fetched",
                "workout_status" => [
                    "phase" => $phaseStatus,
                    "week" => $weekStatus,
                    "day" => $dayStatus
                ]
            ];

        } else {
            $response = [
                "status" => 422,
                "message" => "No Plan Assigned!"
            ];
        }

        return response($response, $response['status']);
    }









    private function updateIsRestDay($plan, $user, $currentPhase, $currentWeekInPhase, $currentDayOfWeek)
    {
        $workout_datas = PlanWorkout::whereHas('plan', function ($query) use ($plan) {
            $query->where('id', $plan->id)
                ->where('is_active', 1);
        })->where(function ($query) {
            $query->whereNull('workout_id')
                ->orWhere('is_rest', 1);
        })->get();

        $lastCompletedWorkout = UserCompletedWorkout::where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->latest('updated_at')
            ->first();

        $lastCompletedDay = $lastCompletedWorkout
            ? $workout_datas->firstWhere('id', $lastCompletedWorkout->plan_workout_id)
            : null;

        if (!empty($workout_datas)) {
            foreach ($workout_datas as $workout) {
                $status = "Completed";

                if (
                    $lastCompletedDay &&
                    (
                        $workout->phase > $lastCompletedDay->phase ||
                        ($workout->phase == $lastCompletedDay->phase && $workout->week > $lastCompletedDay->week) ||
                        ($workout->phase == $lastCompletedDay->phase && $workout->week == $lastCompletedDay->week && $workout->day > $lastCompletedDay->day)
                    )
                ) {
                    $status = $workout->workout_id == null ? "Completed" : "Not Started";
                }

                if (
                    $workout->phase == $currentPhase &&
                    $workout->week == $currentWeekInPhase &&
                    $workout->day < $currentDayOfWeek
                ) {
                    UserCompletedWorkout::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'plan_workout_id' => $workout->id,
                        ],
                        [
                            'status' => $status,
                        ]
                    );
                }

                if (
                    !$lastCompletedWorkout &&
                    $workout->day <= $currentDayOfWeek
                ) {
                    $status = $workout->workout_id == null ? "Completed" : "Not Started";
                    UserCompletedWorkout::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'plan_workout_id' => $workout->id,
                        ],
                        [
                            'status' => $status,
                        ]
                    );
                }
            }
        }
    }

    public function getWorkoutEquipment($id)
    {
        try {
            $equipment = WorkoutEquipment::where('workout_id', $id)->get();
            
            return response()->json([
                'status' => true,
                'message' => 'Equipment retrieved successfully',
                'data' => $equipment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve equipment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getExerciseWarmUp($id)
    {
        try {
            $warmUp = ExerciseWarmup::where('exercise_id', $id)
                ->orderBy('order')
                ->get();
            
            return response()->json([
                'status' => true,
                'message' => 'Warm-up exercises retrieved successfully',
                'data' => $warmUp
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve warm-up exercises',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getExerciseCoolDown($id)
    {
        try {
            $coolDown = ExerciseCooldown::where('exercise_id', $id)
                ->orderBy('order')
                ->get();
            
            return response()->json([
                'status' => true,
                'message' => 'Cool-down exercises retrieved successfully',
                'data' => $coolDown
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve cool-down exercises',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logWorkoutToCalendar(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'workout_id' => 'required|exists:workouts,id',
                'scheduled_date' => 'required|date',
                'scheduled_time' => 'nullable|date_format:H:i',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            $workoutCalendar = WorkoutCalendar::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'workout_id' => $request->workout_id,
                    'scheduled_date' => $request->scheduled_date
                ],
                [
                    'scheduled_time' => $request->scheduled_time,
                    'notes' => $request->notes,
                    'status' => 'scheduled'
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Workout scheduled successfully',
                'data' => $workoutCalendar
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to schedule workout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getWorkoutCalendar($date, Request $request)
    {
        try {
            $user = $request->user();
            
            $workouts = WorkoutCalendar::with('workout')
                ->where('user_id', $user->id)
                ->whereDate('scheduled_date', $date)
                ->orderBy('scheduled_time')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Calendar workouts retrieved successfully',
                'data' => $workouts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve calendar workouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getWeeklyWorkouts($week, Request $request)
    {
        try {
            $user = $request->user();
            $startOfWeek = Carbon::parse($week)->startOfWeek();
            $endOfWeek = Carbon::parse($week)->endOfWeek();
            
            $workouts = WorkoutCalendar::with(['workout', 'workout.exercises'])
                ->where('user_id', $user->id)
                ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
                ->orderBy('scheduled_date')
                ->orderBy('scheduled_time')
                ->get()
                ->groupBy('scheduled_date');

            return response()->json([
                'status' => true,
                'message' => 'Weekly workouts retrieved successfully',
                'data' => $workouts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve weekly workouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workout history with statistics
     * GET /api/customer/workouts/history
     */
    public function getWorkoutHistory(Request $request)
    {
        $user = $request->user();
        $period = $request->query('period', '30days'); // 7days, 30days, 90days, all
        $limit = $request->query('limit', 50);

        $days = match($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            'all' => null,
            default => 30
        };

        try {
            $query = UserCompletedWorkout::where('user_id', $user->id)
                ->with(['workout:id,title,description,duration_minutes,difficulty_level'])
                ->orderBy('completed_at', 'desc');

            if ($days) {
                $query->where('completed_at', '>=', now()->subDays($days));
            }

            $completedWorkouts = $query->limit($limit)->get();

            // Calculate statistics
            $totalWorkouts = $completedWorkouts->count();
            $totalMinutes = $completedWorkouts->sum('actual_duration_minutes');
            $avgDuration = $totalWorkouts > 0 ? round($totalMinutes / $totalWorkouts, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'workouts' => $completedWorkouts,
                    'statistics' => [
                        'total_workouts' => $totalWorkouts,
                        'total_minutes' => $totalMinutes,
                        'avg_duration' => $avgDuration,
                        'period' => $period
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workout history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get personal records for exercises
     * GET /api/customer/workouts/personal-records
     */
    public function getPersonalRecords(Request $request)
    {
        $user = $request->user();
        $exerciseId = $request->query('exercise_id');

        try {
            $query = \DB::table('workout_exercise_logs')
                ->join('exercises', 'workout_exercise_logs.exercise_id', '=', 'exercises.id')
                ->where('workout_exercise_logs.user_id', $user->id)
                ->select(
                    'exercises.id',
                    'exercises.name',
                    'exercises.type',
                    \DB::raw('MAX(workout_exercise_logs.weight) as max_weight'),
                    \DB::raw('MAX(workout_exercise_logs.reps) as max_reps'),
                    \DB::raw('MIN(workout_exercise_logs.time_seconds) as best_time'),
                    \DB::raw('MAX(workout_exercise_logs.distance) as max_distance'),
                    \DB::raw('MAX(workout_exercise_logs.created_at) as last_performed')
                )
                ->groupBy('exercises.id', 'exercises.name', 'exercises.type');

            if ($exerciseId) {
                $query->where('exercises.id', $exerciseId);
            }

            $personalRecords = $query->get();

            return response()->json([
                'success' => true,
                'data' => $personalRecords,
                'count' => $personalRecords->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve personal records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rate and review a completed workout
     * POST /api/customer/workouts/{id}/rate
     */
    public function rateWorkout($id, Request $request)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'difficulty_rating' => 'nullable|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
            'would_recommend' => 'nullable|boolean'
        ]);

        $user = $request->user();

        try {
            // Check if user completed this workout
            $completedWorkout = UserCompletedWorkout::where('user_id', $user->id)
                ->where('workout_id', $id)
                ->orderBy('completed_at', 'desc')
                ->first();

            if (!$completedWorkout) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must complete this workout before rating it'
                ], 403);
            }

            // Create or update rating
            $rating = \DB::table('workout_ratings')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'workout_id' => $id
                ],
                [
                    'rating' => $validated['rating'],
                    'difficulty_rating' => $validated['difficulty_rating'] ?? null,
                    'review' => $validated['review'] ?? null,
                    'would_recommend' => $validated['would_recommend'] ?? true,
                    'updated_at' => now(),
                    'created_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Workout rated successfully',
                'data' => $validated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rate workout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle workout as favorite
     * POST /api/customer/workouts/{id}/favorite
     */
    public function toggleFavorite($id, Request $request)
    {
        $user = $request->user();

        try {
            $workout = Workout::findOrFail($id);

            $favorite = \DB::table('user_favorite_workouts')
                ->where('user_id', $user->id)
                ->where('workout_id', $id)
                ->first();

            if ($favorite) {
                // Remove from favorites
                \DB::table('user_favorite_workouts')
                    ->where('user_id', $user->id)
                    ->where('workout_id', $id)
                    ->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Workout removed from favorites',
                    'is_favorite' => false
                ]);
            } else {
                // Add to favorites
                \DB::table('user_favorite_workouts')->insert([
                    'user_id' => $user->id,
                    'workout_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Workout added to favorites',
                    'is_favorite' => true
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle favorite: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get favorite workouts
     * GET /api/customer/workouts/favorites
     */
    public function getFavorites(Request $request)
    {
        $user = $request->user();

        try {
            $favorites = \DB::table('user_favorite_workouts')
                ->join('workouts', 'user_favorite_workouts.workout_id', '=', 'workouts.id')
                ->where('user_favorite_workouts.user_id', $user->id)
                ->select('workouts.*', 'user_favorite_workouts.created_at as favorited_at')
                ->orderBy('user_favorite_workouts.created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $favorites,
                'count' => $favorites->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve favorites: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workout statistics and analytics
     * GET /api/customer/workouts/statistics
     */
    public function getStatistics(Request $request)
    {
        $user = $request->user();
        $period = $request->query('period', '30days');

        $days = match($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30
        };

        try {
            $since = now()->subDays($days);

            // Total workouts completed
            $totalWorkouts = UserCompletedWorkout::where('user_id', $user->id)
                ->where('completed_at', '>=', $since)
                ->count();

            // Total minutes exercised
            $totalMinutes = UserCompletedWorkout::where('user_id', $user->id)
                ->where('completed_at', '>=', $since)
                ->sum('actual_duration_minutes');

            // Workout streak
            $streak = 0;
            $currentDate = now()->startOfDay();
            while (true) {
                $hasWorkout = UserCompletedWorkout::where('user_id', $user->id)
                    ->whereDate('completed_at', $currentDate)
                    ->exists();

                if ($hasWorkout) {
                    $streak++;
                    $currentDate->subDay();
                } else {
                    break;
                }
            }

            // Most common workout type
            $mostCommonType = UserCompletedWorkout::where('user_id', $user->id)
                ->where('completed_at', '>=', $since)
                ->join('workouts', 'user_completed_workouts.workout_id', '=', 'workouts.id')
                ->select('workouts.type', \DB::raw('COUNT(*) as count'))
                ->groupBy('workouts.type')
                ->orderBy('count', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period . ' (' . $days . ' days)',
                    'total_workouts' => $totalWorkouts,
                    'total_minutes' => $totalMinutes,
                    'avg_minutes_per_day' => round($totalMinutes / $days, 1),
                    'current_streak_days' => $streak,
                    'most_common_type' => $mostCommonType->type ?? 'N/A',
                    'workouts_per_week' => round(($totalWorkouts / $days) * 7, 1)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user workout plan with enhanced calendar data (v2)
     * GET /api/customer/get-user-workout-plan-v2
     */
    public function getUserWorkoutPlanV2(Request $request)
    {
        try {
            $userId = $request->get('user_id', auth()->id());

            // Get active workout plan
            $workoutPlan = DB::table('user_workout_plans')
                ->where('user_id', $userId)
                ->where('is_active', 1)
                ->first();

            if (!$workoutPlan) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No active workout plan found'
                ]);
            }

            // Get all workouts in the plan with calendar integration data
            $workouts = DB::table('workout_plan_workouts')
                ->where('workout_plan_id', $workoutPlan->id)
                ->get()
                ->map(function($workout) {
                    // Get workout details
                    $workoutDetails = DB::table('workouts')->find($workout->workout_id);

                    // Get exercises for this workout
                    $exercises = DB::table('workout_exercises')
                        ->where('workout_id', $workout->workout_id)
                        ->get();

                    return [
                        'id' => $workout->id,
                        'workout_id' => $workout->workout_id,
                        'day_of_week' => $workout->day_of_week,
                        'scheduled_time' => $workout->scheduled_time,
                        'order_in_plan' => $workout->order_in_plan,
                        'is_rest_day' => (bool)$workout->is_rest_day,
                        'workout_details' => [
                            'name' => $workoutDetails->name ?? 'Workout',
                            'description' => $workoutDetails->description ?? '',
                            'duration_minutes' => $workoutDetails->duration_minutes ?? 0,
                            'difficulty_level' => $workoutDetails->difficulty_level ?? 'intermediate',
                            'type' => $workoutDetails->type ?? 'strength',
                        ],
                        'exercises_count' => $exercises->count(),
                        'total_sets' => $exercises->sum('sets'),
                        'calendar_data' => [
                            'title' => $workoutDetails->name ?? 'Workout',
                            'color' => $this->getWorkoutColor($workoutDetails->type ?? 'strength'),
                            'duration' => ($workoutDetails->duration_minutes ?? 0) . ' min',
                            'icon' => $this->getWorkoutIcon($workoutDetails->type ?? 'strength')
                        ]
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'plan_id' => $workoutPlan->id,
                    'plan_name' => $workoutPlan->name,
                    'start_date' => $workoutPlan->start_date,
                    'end_date' => $workoutPlan->end_date,
                    'duration_weeks' => $workoutPlan->duration_weeks,
                    'workouts' => $workouts,
                    'total_workouts_per_week' => $workouts->where('is_rest_day', false)->count(),
                    'rest_days_per_week' => $workouts->where('is_rest_day', true)->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workout plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workout type color for calendar display
     */
    private function getWorkoutColor($type)
    {
        $colors = [
            'strength' => '#4CAF50',
            'cardio' => '#2196F3',
            'flexibility' => '#FF9800',
            'hiit' => '#F44336',
            'yoga' => '#9C27B0',
            'crossfit' => '#607D8B',
            'sports' => '#00BCD4',
            'recovery' => '#8BC34A'
        ];

        return $colors[$type] ?? '#757575';
    }

    /**
     * Get workout type icon for calendar display
     */
    private function getWorkoutIcon($type)
    {
        $icons = [
            'strength' => 'dumbbell',
            'cardio' => 'running',
            'flexibility' => 'spa',
            'hiit' => 'flash',
            'yoga' => 'meditation',
            'crossfit' => 'fitness',
            'sports' => 'sports',
            'recovery' => 'healing'
        ];

        return $icons[$type] ?? 'fitness';
    }
}
