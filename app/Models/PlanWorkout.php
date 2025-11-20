<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanWorkout extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'workout_id',
    ];


    public function workout()
    {
        return $this->belongsTo(Workout::class,'workout_id');
    }
    public function user_workout()
    {
        return $this->hasOne(UserCompletedWorkout::class, 'plan_workout_id', 'id');
    }

    public function userCompletedWorkouts()
    {
        return $this->hasMany(UserCompletedWorkout::class, 'plan_workout_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class,'plan_id');
    }
}
