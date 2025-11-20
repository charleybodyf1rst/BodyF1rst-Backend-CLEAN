<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * HealthMetric Model
 *
 * Stores daily health metrics summary for users
 * One record per user per day with all 29 health metrics
 */
class HealthMetric extends Model
{
    use HasFactory;

    protected $table = 'health_metrics';

    protected $fillable = [
        'user_id',
        'date',
        // Activity Rings
        'active_calories',
        'move_goal',
        'exercise_minutes',
        'exercise_goal',
        'stand_hours',
        'stand_goal',
        // Vital Signs
        'heart_rate',
        'resting_heart_rate',
        'hrv',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'blood_oxygen',
        'respiratory_rate',
        // Body Measurements
        'weight',
        'body_fat',
        'lean_mass',
        'bmi',
        // Fitness Metrics
        'steps',
        'distance',
        'flights_climbed',
        'vo2_max',
        // Nutrition
        'calories_consumed',
        'water_intake',
        // Sleep
        'sleep_hours',
        // Metadata
        'last_sync_source',
        'last_sync_timestamp',
    ];

    protected $casts = [
        'date' => 'date',
        'active_calories' => 'integer',
        'move_goal' => 'integer',
        'exercise_minutes' => 'integer',
        'exercise_goal' => 'integer',
        'stand_hours' => 'integer',
        'stand_goal' => 'integer',
        'heart_rate' => 'integer',
        'resting_heart_rate' => 'integer',
        'hrv' => 'integer',
        'blood_pressure_systolic' => 'integer',
        'blood_pressure_diastolic' => 'integer',
        'blood_oxygen' => 'integer',
        'respiratory_rate' => 'integer',
        'weight' => 'decimal:2',
        'body_fat' => 'decimal:1',
        'lean_mass' => 'decimal:2',
        'bmi' => 'decimal:1',
        'steps' => 'integer',
        'distance' => 'decimal:2',
        'flights_climbed' => 'integer',
        'vo2_max' => 'decimal:1',
        'calories_consumed' => 'integer',
        'water_intake' => 'integer',
        'sleep_hours' => 'decimal:1',
        'last_sync_timestamp' => 'datetime',
    ];

    /**
     * Get the user that owns the health metric
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get today's health metrics for a user
     */
    public static function getToday($userId)
    {
        return self::firstOrCreate(
            [
                'user_id' => $userId,
                'date' => Carbon::today(),
            ],
            [
                'move_goal' => 500,
                'exercise_goal' => 30,
                'stand_goal' => 12,
            ]
        );
    }

    /**
     * Get health metrics for a specific date
     */
    public static function getForDate($userId, $date)
    {
        return self::where('user_id', $userId)
            ->where('date', $date)
            ->first();
    }

    /**
     * Get health metrics range for charts/trends
     */
    public static function getRange($userId, $startDate, $endDate)
    {
        return self::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Calculate activity ring progress percentage
     */
    public function getMoveProgress()
    {
        if ($this->move_goal == 0) return 0;
        return min(($this->active_calories / $this->move_goal) * 100, 100);
    }

    public function getExerciseProgress()
    {
        if ($this->exercise_goal == 0) return 0;
        return min(($this->exercise_minutes / $this->exercise_goal) * 100, 100);
    }

    public function getStandProgress()
    {
        if ($this->stand_goal == 0) return 0;
        return min(($this->stand_hours / $this->stand_goal) * 100, 100);
    }

    /**
     * Format blood pressure as "systolic/diastolic"
     */
    public function getBloodPressureAttribute()
    {
        if ($this->blood_pressure_systolic && $this->blood_pressure_diastolic) {
            return [
                'systolic' => $this->blood_pressure_systolic,
                'diastolic' => $this->blood_pressure_diastolic,
            ];
        }
        return null;
    }

    /**
     * Check if user has met all activity ring goals
     */
    public function hasClosedAllRings()
    {
        return $this->active_calories >= $this->move_goal
            && $this->exercise_minutes >= $this->exercise_goal
            && $this->stand_hours >= $this->stand_goal;
    }
}
