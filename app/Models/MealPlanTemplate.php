<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealPlanTemplate extends Model
{
    use HasFactory;

    protected $table = 'meal_plan_templates';

    protected $fillable = [
        'creator_id',
        'creator_type',
        'name',
        'description',
        'goal',
        'category',
        'duration_days',
        'daily_calories',
        'daily_protein_g',
        'daily_carbs_g',
        'daily_fat_g',
        'meals_structure',
        'meal_templates',
        'tags',
        'is_public',
        'is_featured',
        'use_count',
        'instructions',
        'shopping_list',
        'prep_tips',
        'cloned_from',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'daily_calories' => 'integer',
        'daily_protein_g' => 'decimal:2',
        'daily_carbs_g' => 'decimal:2',
        'daily_fat_g' => 'decimal:2',
        'meals_structure' => 'array',
        'meal_templates' => 'array',
        'tags' => 'array',
        'shopping_list' => 'array',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'use_count' => 'integer',
    ];

    /**
     * Relationships
     */

    /**
     * Polymorphic relationship to creator (Admin or Coach)
     */
    public function creator()
    {
        return $this->morphTo('creator', 'creator_type', 'creator_id');
    }

    /**
     * Get users who have this meal plan template assigned (directly)
     */
    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'meal_plan_assignments', 'meal_plan_template_id', 'user_id')
            ->whereNull('organization_id')
            ->withPivot(['assigned_by', 'assigner_type', 'start_date', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get organizations that have this meal plan template assigned
     */
    public function assignedOrganizations()
    {
        return $this->belongsToMany(Organization::class, 'meal_plan_assignments', 'meal_plan_template_id', 'organization_id')
            ->whereNull('user_id')
            ->withPivot(['assigned_by', 'assigner_type', 'start_date', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get the original meal plan template if this is a clone
     */
    public function originalPlan()
    {
        return $this->belongsTo(MealPlanTemplate::class, 'cloned_from');
    }

    /**
     * Get all clones of this meal plan template
     */
    public function clones()
    {
        return $this->hasMany(MealPlanTemplate::class, 'cloned_from');
    }

    /**
     * Accessors & Helpers
     */

    /**
     * Get macro percentages
     */
    public function getMacroPercentagesAttribute()
    {
        if ($this->daily_calories == 0) {
            return ['protein' => 0, 'carbs' => 0, 'fat' => 0];
        }

        $proteinCal = $this->daily_protein_g * 4;
        $carbsCal = $this->daily_carbs_g * 4;
        $fatCal = $this->daily_fat_g * 9;

        return [
            'protein' => round(($proteinCal / $this->daily_calories) * 100, 1),
            'carbs' => round(($carbsCal / $this->daily_calories) * 100, 1),
            'fat' => round(($fatCal / $this->daily_calories) * 100, 1),
        ];
    }

    /**
     * Get total number of meals in the plan
     */
    public function getTotalMealsAttribute()
    {
        if (!$this->meal_templates || !is_array($this->meal_templates)) {
            return 0;
        }

        $total = 0;
        foreach ($this->meal_templates as $day) {
            if (isset($day['meals']) && is_array($day['meals'])) {
                $total += count($day['meals']);
            }
        }

        return $total;
    }

    /**
     * Get formatted duration string
     */
    public function getDurationFormattedAttribute()
    {
        if ($this->duration_days == 1) {
            return '1 day';
        } elseif ($this->duration_days == 7) {
            return '1 week';
        } elseif ($this->duration_days % 7 == 0) {
            $weeks = $this->duration_days / 7;
            return "{$weeks} weeks";
        } elseif ($this->duration_days >= 30) {
            $months = round($this->duration_days / 30);
            return "{$months} months";
        } else {
            return "{$this->duration_days} days";
        }
    }

    /**
     * Scopes
     */

    /**
     * Scope: Public meal plan templates
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope: Featured meal plan templates
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: By goal
     */
    public function scopeByGoal($query, $goal)
    {
        return $query->where('goal', $goal);
    }

    /**
     * Scope: By category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: By duration
     */
    public function scopeByDuration($query, $days)
    {
        return $query->where('duration_days', $days);
    }

    /**
     * Scope: Calorie range
     */
    public function scopeCalorieRange($query, $min, $max)
    {
        return $query->whereBetween('daily_calories', [$min, $max]);
    }

    /**
     * Scope: Created by specific user
     */
    public function scopeCreatedBy($query, $creatorId, $creatorType = null)
    {
        $query->where('creator_id', $creatorId);

        if ($creatorType) {
            $query->where('creator_type', $creatorType);
        }

        return $query;
    }

    /**
     * Scope: Available to user (public or created by them)
     */
    public function scopeAvailableToUser($query, $userId, $userType = null)
    {
        return $query->where(function ($q) use ($userId, $userType) {
            $q->where('is_public', true)
              ->orWhere(function ($subQ) use ($userId, $userType) {
                  $subQ->where('creator_id', $userId);
                  if ($userType) {
                      $subQ->where('creator_type', $userType);
                  }
              });
        });
    }

    /**
     * Increment use count
     */
    public function incrementUseCount($amount = 1)
    {
        $this->increment('use_count', $amount);
    }

    /**
     * Check if meal plan template is assigned to a specific user
     */
    public function isAssignedToUser($userId)
    {
        return $this->assignedUsers()->where('users.id', $userId)->exists();
    }

    /**
     * Check if meal plan template is assigned to a specific organization
     */
    public function isAssignedToOrganization($orgId)
    {
        return $this->assignedOrganizations()->where('organizations.id', $orgId)->exists();
    }
}
