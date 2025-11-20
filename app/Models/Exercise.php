<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exercise extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['title','description','tags'];

    protected $casts = [
        "uploaded_by" => "integer",
        "tags" => "array",
        "parent_id" => "integer"
    ];

    public function upload_by()
    {
        return $this->morphTo('upload_by','uploader','uploaded_by');
    }

    public function videos_pivot()
    {
        return $this->belongsToMany(Video::class, ExerciseVideo::class, 'exercise_id', 'video_id')->withTimestamps();
    }
    public function video_pivot()
    {
        return $this->belongsTo(Video::class, ExerciseVideo::class, 'exercise_id', 'video_id')->withTimestamps();
    }

    public function videos()
    {
        return $this->hasManyThrough(Video::class, ExerciseVideo::class, 'exercise_id', 'id', 'id', 'video_id');
    }
    public function video()
    {
        return $this->hasOneThrough(Video::class, ExerciseVideo::class, 'exercise_id', 'id', 'id', 'video_id');
    }

    public function workouts()
    {
        return $this->hasManyThrough(Workout::class, WorkoutExercise::class, 'exercise_id', 'id', 'id', 'workout_id');
    }
}
