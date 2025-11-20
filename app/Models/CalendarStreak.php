<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CalendarStreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'streak_type',
        'current_streak',
        'current_streak_start',
        'last_activity_date',
        'longest_streak',
        'longest_streak_start',
        'longest_streak_end',
        'total_activities',
        'activity_dates',
        'milestones',
        'streak_freezes_available',
        'streak_freezes_used',
    ];

    protected $casts = [
        'current_streak_start' => 'date',
        'last_activity_date' => 'date',
        'longest_streak_start' => 'date',
        'longest_streak_end' => 'date',
        'activity_dates' => 'array',
        'milestones' => 'array',
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'total_activities' => 'integer',
        'streak_freezes_available' => 'integer',
        'streak_freezes_used' => 'integer',
    ];

    // Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('streak_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('current_streak', '>', 0);
    }

    // Methods

    public function recordActivity($date = null)
    {
        $date = $date ? Carbon::parse($date) : today();
        $dateString = $date->format('Y-m-d');

        // Get activity dates array
        $activityDates = $this->activity_dates ?? [];

        // Check if already recorded today
        if (in_array($dateString, $activityDates)) {
            return $this;
        }

        // Add to activity dates (keep last 365 days)
        $activityDates[] = $dateString;
        $activityDates = array_unique($activityDates);
        rsort($activityDates);
        $this->activity_dates = array_slice($activityDates, 0, 365);

        // Increment total activities
        $this->total_activities++;

        // Update streak
        if (!$this->last_activity_date) {
            // First activity
            $this->current_streak = 1;
            $this->current_streak_start = $date;
        } else {
            $daysSinceLastActivity = $this->last_activity_date->diffInDays($date);

            if ($daysSinceLastActivity === 1) {
                // Consecutive day - increment streak
                $this->current_streak++;
            } elseif ($daysSinceLastActivity === 0) {
                // Same day - no change
                return $this;
            } else {
                // Streak broken - check if we can use a freeze
                if ($this->streak_freezes_available > 0 && $daysSinceLastActivity === 2) {
                    // Use a freeze to maintain streak
                    $this->streak_freezes_available--;
                    $this->streak_freezes_used++;
                    $this->current_streak++;
                } else {
                    // Reset streak
                    $this->current_streak = 1;
                    $this->current_streak_start = $date;
                }
            }
        }

        $this->last_activity_date = $date;

        // Check if current streak is longest
        if ($this->current_streak > $this->longest_streak) {
            $this->longest_streak = $this->current_streak;
            $this->longest_streak_start = $this->current_streak_start;
            $this->longest_streak_end = $date;
        }

        // Check milestones
        $this->checkMilestones();

        $this->save();

        return $this;
    }

    public function breakStreak()
    {
        if ($this->current_streak > 0 && $this->current_streak === $this->longest_streak) {
            $this->longest_streak_end = $this->last_activity_date;
        }

        $this->current_streak = 0;
        $this->current_streak_start = null;
        $this->save();

        return $this;
    }

    public function checkStreak()
    {
        if (!$this->last_activity_date) {
            return;
        }

        $daysSinceLastActivity = $this->last_activity_date->diffInDays(today());

        // If more than 1 day has passed (and no freezes available), break streak
        if ($daysSinceLastActivity > 1 && $this->streak_freezes_available === 0) {
            $this->breakStreak();
        }
    }

    protected function checkMilestones()
    {
        $milestones = $this->milestones ?? [];
        $milestoneDays = [7, 14, 30, 60, 90, 180, 365];

        foreach ($milestoneDays as $days) {
            if ($this->current_streak === $days && !in_array($days, $milestones)) {
                $milestones[] = $days;

                // Award bonus body points for milestones
                $this->awardMilestoneBonus($days);
            }
        }

        $this->milestones = $milestones;
    }

    protected function awardMilestoneBonus($days)
    {
        // Award bonus points based on milestone
        $bonusPoints = match($days) {
            7 => 50,
            14 => 100,
            30 => 250,
            60 => 500,
            90 => 750,
            180 => 1500,
            365 => 3650,
            default => 0,
        };

        if ($bonusPoints > 0 && $this->user) {
            BodyPoint::create([
                'user_id' => $this->user_id,
                'points' => $bonusPoints,
                'activity_type' => 'streak_milestone',
                'description' => ucfirst($this->streak_type) . " streak milestone: {$days} days",
                'metadata' => json_encode([
                    'streak_type' => $this->streak_type,
                    'milestone_days' => $days,
                ]),
            ]);

            // Update user's total body points
            $this->user->increment('body_points', $bonusPoints);
        }
    }

    public function getHeatMapData($days = 365)
    {
        $activityDates = $this->activity_dates ?? [];
        $heatMap = [];

        $startDate = today()->subDays($days - 1);

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateString = $date->format('Y-m-d');

            $heatMap[$dateString] = [
                'date' => $dateString,
                'count' => in_array($dateString, $activityDates) ? 1 : 0,
                'level' => in_array($dateString, $activityDates) ? 4 : 0, // GitHub style 0-4
            ];
        }

        return array_values($heatMap);
    }

    public function addStreakFreeze($count = 1)
    {
        $this->streak_freezes_available += $count;
        $this->save();
    }

    public function getStreakHealth()
    {
        if (!$this->last_activity_date) {
            return 'new';
        }

        $daysSinceLastActivity = $this->last_activity_date->diffInDays(today());

        if ($daysSinceLastActivity === 0) {
            return 'excellent'; // Active today
        } elseif ($daysSinceLastActivity === 1) {
            return 'at_risk'; // Could break tomorrow
        } else {
            return 'broken';
        }
    }
}
