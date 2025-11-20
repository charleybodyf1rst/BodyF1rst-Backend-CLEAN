<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyCheckin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'coach_id',
        'checkin_date',
        'week_number',
        'current_weight',
        'weight_unit',
        'body_fat_percentage',
        'measurements',
        'front_photo',
        'side_photo',
        'back_photo',
        'energy_level',
        'mood',
        'sleep_quality',
        'sleep_hours',
        'stress_level',
        'workouts_completed',
        'workouts_planned',
        'meals_logged',
        'water_intake_oz',
        'what_went_well',
        'challenges_faced',
        'goals_next_week',
        'questions_for_coach',
        'additional_notes',
        'coach_feedback',
        'coach_reviewed_at',
        'coach_recommendations',
        'status',
        'reminder_sent',
        'reminder_sent_at',
        'submitted_at',
    ];

    protected $casts = [
        'checkin_date' => 'date',
        'current_weight' => 'decimal:2',
        'body_fat_percentage' => 'decimal:2',
        'measurements' => 'array',
        'sleep_hours' => 'decimal:2',
        'water_intake_oz' => 'decimal:2',
        'coach_recommendations' => 'array',
        'reminder_sent' => 'boolean',
        'coach_reviewed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    /**
     * Get the client user who owns the check-in
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the coach who reviews the check-in
     */
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Calculate compliance rate for this check-in
     */
    public function getComplianceRateAttribute()
    {
        if ($this->workouts_planned == 0) {
            return 0;
        }
        return round(($this->workouts_completed / $this->workouts_planned) * 100, 2);
    }

    /**
     * Check if check-in is overdue (not submitted within 7 days of checkin_date)
     */
    public function getIsOverdueAttribute()
    {
        if ($this->status !== 'pending') {
            return false;
        }
        return $this->checkin_date->addDays(7)->isPast();
    }

    /**
     * Get overall wellness score (average of energy, mood, sleep quality)
     */
    public function getWellnessScoreAttribute()
    {
        $scores = array_filter([
            $this->energy_level,
            $this->mood,
            $this->sleep_quality
        ]);

        if (empty($scores)) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 1);
    }

    /**
     * Scope for pending check-ins
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for submitted check-ins
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope for reviewed check-ins
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Scope for check-ins by specific client
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('user_id', $clientId);
    }

    /**
     * Scope for check-ins by specific coach
     */
    public function scopeForCoach($query, $coachId)
    {
        return $query->where('coach_id', $coachId);
    }

    /**
     * Scope for check-ins within a date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('checkin_date', [$startDate, $endDate]);
    }
}
