<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCompletedWorkout extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'plan_id', 'plan_workout_id', 'workout_id', 'workout_exercise_id', 'exercise_id', 'status'];

    protected $casts = [
        'user_id' => 'integer',
        'plan_id' => 'integer',
        'plan_workout_id' => 'integer',
        'workout_id' => 'integer',
        'workout_exercise_id' => 'integer',
        'exercise_id' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
