<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'meal_type',
        'foods',
        'total_calories',
        'total_protein_g',
        'total_carbs_g',
        'total_fat_g',
        'total_fiber_g',
        'is_public',
        'use_count',
        'category',
        'tags',
        'prep_time_minutes',
        'cook_time_minutes',
        'instructions',
        'image_url'
    ];

    protected $casts = [
        'foods' => 'array',
        'tags' => 'array',
        'is_public' => 'boolean',
        'use_count' => 'integer',
        'total_calories' => 'integer',
        'total_protein_g' => 'decimal:2',
        'total_carbs_g' => 'decimal:2',
        'total_fat_g' => 'decimal:2',
        'total_fiber_g' => 'decimal:2',
        'prep_time_minutes' => 'integer',
        'cook_time_minutes' => 'integer',
    ];

    /**
     * Relationships
     */
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function planAssignments()
    {
        return $this->hasMany(MealTemplatePlanAssignment::class);
    }

    /**
     * Calculate and update meal totals from foods array
     */
    public function calculateTotals()
    {
        if (!$this->foods || !is_array($this->foods)) {
            return;
        }

        $totals = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'fat' => 0,
            'fiber' => 0
        ];

        foreach ($this->foods as $food) {
            $quantity = $food['quantity'] ?? 1;
            $totals['calories'] += ($food['calories'] ?? 0) * $quantity;
            $totals['protein'] += ($food['protein'] ?? 0) * $quantity;
            $totals['carbs'] += ($food['carbs'] ?? 0) * $quantity;
            $totals['fat'] += ($food['fat'] ?? 0) * $quantity;
            $totals['fiber'] += ($food['fiber'] ?? 0) * $quantity;
        }

        $this->total_calories = round($totals['calories']);
        $this->total_protein_g = round($totals['protein'], 2);
        $this->total_carbs_g = round($totals['carbs'], 2);
        $this->total_fat_g = round($totals['fat'], 2);
        $this->total_fiber_g = round($totals['fiber'], 2);
    }

    /**
     * Increment use count when template is used
     */
    public function incrementUseCount()
    {
        $this->increment('use_count');
    }

    /**
     * Accessor for macro percentages
     */
    public function getMacroPercentagesAttribute()
    {
        if ($this->total_calories == 0) {
            return ['protein' => 0, 'carbs' => 0, 'fat' => 0];
        }

        // Calculate calories from each macro
        $proteinCal = $this->total_protein_g * 4;
        $carbsCal = $this->total_carbs_g * 4;
        $fatCal = $this->total_fat_g * 9;

        return [
            'protein' => round(($proteinCal / $this->total_calories) * 100, 1),
            'carbs' => round(($carbsCal / $this->total_calories) * 100, 1),
            'fat' => round(($fatCal / $this->total_calories) * 100, 1),
        ];
    }

    /**
     * Get total preparation time
     */
    public function getTotalTimeAttribute()
    {
        return ($this->prep_time_minutes ?? 0) + ($this->cook_time_minutes ?? 0);
    }

    /**
     * Scope: Public templates
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope: By meal type
     */
    public function scopeByMealType($query, $mealType)
    {
        return $query->where('meal_type', $mealType);
    }

    /**
     * Scope: By category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: By coach or public
     */
    public function scopeAvailableToCoach($query, $coachId)
    {
        return $query->where(function ($q) use ($coachId) {
            $q->where('coach_id', $coachId)
              ->orWhere('is_public', true);
        });
    }
}
