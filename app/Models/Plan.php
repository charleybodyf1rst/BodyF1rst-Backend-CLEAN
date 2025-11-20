<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['title', 'type', 'phase', 'week'];

    protected $appends = ['total_phases', 'total_weeks', 'total_days'];

    protected $casts = [
        "phase" => "integer",
        "week" => "integer",
        "uploaded_by" => "integer",
        "parent_id" => "integer"
    ];

    public function upload_by()
    {
        return $this->morphTo('upload_by', 'uploader', 'uploaded_by');
    }

    public function workouts()
    {
        return $this->hasMany(PlanWorkout::class, 'plan_id', 'id');
    }

    public function users()
    {
        return $this->hasManyThrough(User::class, AssignPlan::class, 'plan_id', 'id', 'id', 'user_id');
    }
    public function organizations()
    {
        return $this->hasManyThrough(Organization::class, AssignPlan::class, 'plan_id', 'id', 'id', 'organization_id');
    }

    public function assignedOrganizations()
    {
        return $this->hasMany(AssignPlan::class, 'plan_id', 'id');
    }

    public function getTotalWeeksAttribute()
    {
        return $this->workouts()
            ->selectRaw('MAX(week) as total_weeks') // Get the maximum week for each phase
            ->groupBy('phase') // Group by phase to ensure we get the max week per phase
            ->get()
            ->sum('total_weeks'); // Sum all the total weeks from each phase
    }

    public function getTotalPhasesAttribute()
    {
        return $this->workouts()
            ->distinct('phase')
            ->count('phase');
    }

    public function getTotalDaysAttribute()
    {
        return $this->workouts()
            ->selectRaw('phase, week, day') // Select unique identifiers
            ->groupBy('phase', 'week', 'day') // Group by phase, week, and day to ensure uniqueness
            ->get()
            ->count(); // Count the grouped records
    }

    public function totalWeeks()
    {
        return $this->workouts()
            ->selectRaw('MAX(week) as total_weeks') // Get the maximum week for each phase
            ->groupBy('phase') // Group by phase to ensure we get the max week per phase
            ->get()
            ->sum('total_weeks'); // Sum all the total weeks from each phase
    }
    public function totalPhases()
    {
        return $this->workouts()
            ->distinct('phase')
            ->count('phase');
    }

    public function totalDays()
    {
        return $this->workouts()
            ->selectRaw('phase, week, day') // Select unique identifiers
            ->groupBy('phase', 'week', 'day') // Group by phase, week, and day to ensure uniqueness
            ->get()
            ->count(); // Count the grouped records
    }

    public function totalData($plan_id)
    {
        return $this->selectRaw(
            '(SELECT COUNT(DISTINCT phase) FROM plan_workouts WHERE plan_workouts.plan_id = ' . $plan_id . ') as total_phases, ' .
                '(SELECT COUNT(DISTINCT CONCAT(phase, "-", week)) FROM plan_workouts WHERE plan_workouts.plan_id = ' . $plan_id . ') as total_weeks, ' .
                '(SELECT COUNT(DISTINCT CONCAT(phase, "-", week, "-", day)) FROM plan_workouts WHERE plan_workouts.plan_id = ' . $plan_id . ') as total_days'
        )
            ->groupBy('plans.id') // Group by plan_id to get the totals per plan
            ->first(); // Fetch the results
    }

    public function user_workouts()
    {
        return $this->hasMany(UserCompletedWorkout::class, 'plan_id', 'id');
    }
}
