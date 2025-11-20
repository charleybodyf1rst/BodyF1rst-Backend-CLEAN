<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $exercises = $this->exercises ?? null;
        $exercise = $exercises ? collect($exercises)->first(function ($ex) {
            return $ex->is_rest == 0;
        }) : null;
        $videos = $exercise ? $exercise->videos : null;
        $video = $videos ? $videos[0] : null;

        return [
            'id' => $this->id,
            "title" => $this->title,
            "is_active" => $this->is_active,
            "visibility_type" => $this->visibility_type,
            "thumbnail" => $video->video_thumbnail ?? null,
            "exercises" => $this->exercises->map(function($exercise){
                return [
                    "id" => $exercise->id,
                    "workout_id" => $exercise->workout_id,
                    "exercise_id" => $exercise->exercise_id,
                    "type" => $exercise->type,
                    "superset" => $exercise->superset,
                    "is_stag" => $exercise->is_stag,
                    "stag" => $exercise->stag,
                    "stagger" => $exercise->stagger,
                    "min" => $exercise->min,
                    "sec" => $exercise->sec,
                    "set" => $exercise->set,
                    "rep" => $exercise->rep,
                    "is_rest" => $exercise->is_rest,
                    "rest_min" => $exercise->rest_min,
                    "rest_sec" => $exercise->rest_sec,
                    "sort" => $exercise->sort,
                    "created_at" => $exercise->created_at,
                    "updated_at" => $exercise->updated_at,
                    "workout_exercise_id" => $exercise->workout_exercise_id,
                    "title" => $exercise->title,
                    "tags" => $exercise->tags,
                    "description" => $exercise->description,
                    "videos" => $exercise->videos,
                    "status" => $this->user_exercises->where('plan_workout_id',$this->plan_workout_id)->where('workout_exercise_id', $exercise->workout_exercise_id)->first()?->status ?? "Not Started",
                    "start_time" => $this->user_exercises->where('plan_workout_id',$this->plan_workout_id)->where('workout_exercise_id', $exercise->workout_exercise_id)->first()?->start_time ?? null,
                    "end_time" => $this->user_exercises->where('plan_workout_id',$this->plan_workout_id)->where('workout_exercise_id', $exercise->workout_exercise_id)->first()?->end_time ?? null,
                ];
            }),
            "rest_count" => $this->rest_count,
            "exercise_count" => $this->exercise_count,
            "body_points" => $this->body_points ?? 0
        ];
    }
}
