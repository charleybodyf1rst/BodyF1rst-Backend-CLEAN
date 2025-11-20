<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class WorkoutExercise extends Model
{
    use HasFactory;

    protected $fillable = ["workout_id","exercise_id","type","min","sec","set","rep","is_rest","rest_min","rest_sec","sort","is_stag","stagger","superset"];

    protected $casts = [
        "workout_id" => "integer",
        "exercise_id" => "integer",
        "is_stag" => "boolean",
        "stagger" => "array"
    ];

    public function getStaggerAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null) {
            return json_decode($value);
        } else {
            return null;
        }
    }

    public function workout()
    {
        return $this->belongsTo(Workout::class,'workout_id');
    }
    public function exercise()
    {
        return $this->belongsTo(Exercise::class,'exercise_id');
    }

    public function user_exercise()
    {
        return $this->belongsTo(UserCompletedWorkout::class, 'workout_exercise_id', 'id');
    }
    public function userCompletedWorkouts()
    {
        return $this->hasMany(UserCompletedWorkout::class, 'workout_exercise_id');
    }
    public function videos()
    {
        $path = url("/") . "/upload/videos/";
        $paththumbnail = url("/") . "/upload/videos/thumbnails/";

        return $this->hasMany(Exercise::class, 'id', 'exercise_id')
            ->leftJoin("exercise_videos", "exercise_videos.exercise_id", "=", "exercises.id")
            ->leftJoin("videos", "videos.id", "=", "exercise_videos.video_id")
            ->whereNull("videos.deleted_at")
            ->select(
                "videos.*",
                DB::raw("CONCAT('$path', videos.video_file) as video_file"),
                DB::raw("CONCAT('$paththumbnail', videos.video_thumbnail) as video_thumbnail"),
                'exercise_videos.*',
                'exercise_videos.id as exercise_video_id',
                'exercises.*',
                'exercises.id as exercise_id'
            );
    }

    public function video()
    {
        $path = url("/") . "/upload/videos/";
        $paththumbnail = url("/") . "/upload/videos/thumbnails/";

        return $this->hasOne(Exercise::class, 'id', 'exercise_id')
            ->leftJoin("exercise_videos", "exercise_videos.exercise_id", "=", "exercises.id")
            ->leftJoin("videos", "videos.id", "=", "exercise_videos.video_id")
            ->whereNull("videos.deleted_at")
            ->select(
                "videos.*",
                DB::raw("CONCAT('$path', videos.video_file) as video_file"),
                DB::raw("CONCAT('$paththumbnail', videos.video_thumbnail) as video_thumbnail"),
                'exercise_videos.*',
                'exercise_videos.id as exercise_video_id',
                'exercises.*',
                'exercises.id as exercise_id'
            );
    }

}
