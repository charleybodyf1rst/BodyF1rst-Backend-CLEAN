<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NutritionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'start_date',
        'duration_days',
        'daily_calories',
        'daily_protein_g',
        'daily_carbs_g',
        'daily_fat_g',
        'bmr',
        'tdee',
        'goal_type',
        'activity_level',
        'meals',
        'notes'
    ];

    protected $casts = [
        'meals' => 'array',
        'start_date' => 'date',
        'daily_protein_g' => 'decimal:2',
        'daily_carbs_g' => 'decimal:2',
        'daily_fat_g' => 'decimal:2',
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
        return $this->hasMany(NutritionPlanAssignment::class);
    }
}
