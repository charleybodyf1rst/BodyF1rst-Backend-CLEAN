<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NutritionPlanLibrary extends Model
{
    use HasFactory;

    protected $table = 'nutrition_plan_library';

    protected $fillable = [
        'created_by_admin_id',
        'name',
        'description',
        'duration_days',
        'daily_calories',
        'daily_protein_g',
        'daily_carbs_g',
        'daily_fat_g',
        'goal_type',
        'activity_level',
        'meals',
        'tags',
        'thumbnail_url',
        'is_featured',
        'clone_count',
        'notes'
    ];

    protected $casts = [
        'meals' => 'array',
        'tags' => 'array',
        'is_featured' => 'boolean',
        'clone_count' => 'integer',
        'duration_days' => 'integer',
        'daily_calories' => 'integer',
        'daily_protein_g' => 'decimal:2',
        'daily_carbs_g' => 'decimal:2',
        'daily_fat_g' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the admin who created this plan
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    /**
     * Scope to get featured plans
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to filter by goal type
     */
    public function scopeGoalType($query, $goalType)
    {
        return $query->where('goal_type', $goalType);
    }

    /**
     * Increment clone count
     */
    public function incrementCloneCount()
    {
        $this->increment('clone_count');
    }
}
