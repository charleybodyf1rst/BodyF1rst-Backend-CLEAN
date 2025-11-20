<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanWorkoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $plan_workout = $this ?? null;
        $workout = $this->workout ?? null;
        $user_exercises = $workout ? $workout->user_exercises : null;
        $exercises_count = $workout ? $workout->exercises_count : 0;
        $exercise = $workout->exercise ?? null;
        $video = $exercise->video ?? null;
        $user_exercises_count = $user_exercises ? $user_exercises->where('plan_workout_id', $this->id)->count() : 0;
        if(!$workout)
        {
            $exercises_count = 1;
            $user_exercises_count = $plan_workout->user_workout ? $plan_workout->user_workout->where('plan_workout_id',$plan_workout->id)->where('status',"Completed")->count() : 0;
        }
        else
        {
            $exercises_count = $workout ? $workout->exercises_count : 0;
            $user_exercises_count = $user_exercises ? $user_exercises->where('plan_workout_id', $this->id)->count() : 0;
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

        return [
            'id' => $this->id,
            'workout_id' => $workout ? $workout->id : null,
            'title' => $this->is_rest ? "Rest Day" : ($workout ? $workout->title : null),
            'phase' => $this->phase ?? null,
            'week' => $this->week ?? null,
            'day' => $this->day ?? null,
            'is_rest' => $this->is_rest,
            'sort' => $this->sort,
            'thumbnail' => $video ? $video->video_thumbnail : null,
            'status' => $status ?? "Not Started",
            'is_assigned' => $this->is_assigned ?? 0,
            'start_time' => $workout ? $workout->user_exercises->where('plan_workout_id',$this->id)->sortBy('start_time')->first()?->start_time : null,
            'end_time' => $workout ? $workout->user_exercises->where('plan_workout_id',$this->id)->sortByDesc('end_time')->first()?->end_time : null,
        ];
    }
}
