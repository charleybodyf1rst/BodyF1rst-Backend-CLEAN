<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workout extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ["title"];

    protected $casts = [
        "uploaded_by" => "integer",
        "parent_id" => "integer"
    ];

    public function upload_by()
    {
        return $this->morphTo('upload_by','uploader','uploaded_by');
    }

    // public function exercises()
    // {
    //     return $this->hasManyThrough(Exercise::class, WorkoutExercise::class, 'workout_id', 'id', 'id', 'exercise_id');
    // }

    public function exercises()
    {
        return $this->hasMany(WorkoutExercise::class, 'workout_id', 'id');
    }
    public function user_exercises()
    {
        return $this->hasMany(UserCompletedWorkout::class, 'workout_id', 'id');
    }
    public function exercise_pivots()
    {
        return $this->belongsToMany(Exercise::class, 'workout_exercises', 'workout_id', 'exercise_id')->withTimestamps();
    }
    public function exercise()
    {
        return $this->hasOne(WorkoutExercise::class, 'workout_id', 'id')->where('is_rest',0);
    }

    public function workoutExercises()
    {
        return $this->hasMany(WorkoutExercise::class);
    }

    public function plans()
    {
        return $this->hasMany(PlanWorkout::class, 'workout_id', 'id');
    }
    public function plans_data()
    {
        return $this->hasManyThrough(Plan::class, PlanWorkout::class, 'workout_id', 'id', 'id', 'plan_id');
    }
}
