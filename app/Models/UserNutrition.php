<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserNutrition extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'calories_consumed',
        'calories_target',
        'protein_consumed',
        'protein_target',
        'carbs_consumed',
        'carbs_target',
        'fats_consumed',
        'fats_target',
        'sync_source',
        'last_synced_at'
    ];

    protected $casts = [
        'date' => 'date',
        'calories_consumed' => 'float',
        'calories_target' => 'float',
        'protein_consumed' => 'float',
        'protein_target' => 'float',
        'carbs_consumed' => 'float',
        'carbs_target' => 'float',
        'fats_consumed' => 'float',
        'fats_target' => 'float',
        'last_synced_at' => 'datetime'
    ];

    /**
     * Get the user that owns the nutrition record
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get nutrition for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get nutrition for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', Carbon::today());
    }

    /**
     * Scope to get nutrition for a specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope to get nutrition within date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Get calorie completion percentage
     */
    public function getCaloriePercentageAttribute()
    {
        return $this->calories_target > 0 
            ? min(100, ($this->calories_consumed / $this->calories_target) * 100) 
            : 0;
    }

    /**
     * Get protein completion percentage
     */
    public function getProteinPercentageAttribute()
    {
        return $this->protein_target > 0 
            ? min(100, ($this->protein_consumed / $this->protein_target) * 100) 
            : 0;
    }

    /**
     * Get carbs completion percentage
     */
    public function getCarbsPercentageAttribute()
    {
        return $this->carbs_target > 0 
            ? min(100, ($this->carbs_consumed / $this->carbs_target) * 100) 
            : 0;
    }

    /**
     * Get fats completion percentage
     */
    public function getFatsPercentageAttribute()
    {
        return $this->fats_target > 0 
            ? min(100, ($this->fats_consumed / $this->fats_target) * 100) 
            : 0;
    }

    /**
     * Get overall nutrition completion percentage
     */
    public function getOverallPercentageAttribute()
    {
        return ($this->calorie_percentage + $this->protein_percentage + 
                $this->carbs_percentage + $this->fats_percentage) / 4;
    }

    /**
     * Check if nutrition data was synced from external source
     */
    public function getIsSyncedAttribute()
    {
        return !is_null($this->sync_source);
    }

    /**
     * Get remaining calories
     */
    public function getRemainingCaloriesAttribute()
    {
        return max(0, $this->calories_target - $this->calories_consumed);
    }

    /**
     * Get remaining protein
     */
    public function getRemainingProteinAttribute()
    {
        return max(0, $this->protein_target - $this->protein_consumed);
    }

    /**
     * Get remaining carbs
     */
    public function getRemainingCarbsAttribute()
    {
        return max(0, $this->carbs_target - $this->carbs_consumed);
    }

    /**
     * Get remaining fats
     */
    public function getRemainingFatsAttribute()
    {
        return max(0, $this->fats_target - $this->fats_consumed);
    }
}
