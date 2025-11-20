<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'duration_weeks',
        'sessions_per_week',
        'difficulty_level',
        'goal',
        'exercises',
        'notes'
    ];

    protected $casts = [
        'exercises' => 'array',
    ];

    /**
     * Get the coach who created this plan
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Get all assignments for this plan
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WorkoutPlanAssignment::class);
    }
}
