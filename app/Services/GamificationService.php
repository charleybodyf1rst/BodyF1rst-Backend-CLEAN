<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Gamification Service
 *
 * Manages body points, achievements, badges, streaks, and rewards
 * for all user activities including workouts, nutrition, weigh-ins,
 * scheduling, challenges, and progress milestones.
 *
 * Based on research from top fitness apps 2025:
 * - Workout Quest: RPG-style leveling with EXP points
 * - Nike Run Club: 67% participation in weekly challenges
 * - Fitbit: 100 badges for various achievements
 * - BetterPoints: Bonus points for consecutive day streaks
 */
class GamificationService
{
    /**
     * Body Points Configuration
     * Based on best practices from top fitness apps
     */
    const POINTS = [
        // Workout Activities
        'workout_completed' => 50,
        'workout_completed_rx' => 75,  // Completed as prescribed (RX)
        'workout_streak_bonus' => 25,  // Per day in streak
        'first_workout_of_day' => 30,
        'workout_pr' => 100,  // Personal record achieved

        // Nutrition Activities
        'meal_logged' => 15,
        'daily_nutrition_goal_met' => 40,
        'weekly_nutrition_compliance' => 100,  // 6+ days on track
        'meal_prep_logged' => 30,
        'water_goal_met' => 20,

        // Body Measurements
        'weight_logged' => 25,
        'weekly_weigh_in_streak' => 50,
        'body_measurement_logged' => 20,
        'progress_photo_uploaded' => 35,

        // Scheduling & Planning
        'workout_scheduled' => 10,
        'meal_plan_created' => 40,
        'weekly_plan_completed' => 150,
        'appointment_attended' => 30,

        // Challenges
        'challenge_joined' => 50,
        'daily_challenge_completed' => 60,
        'challenge_milestone' => 80,
        'challenge_completed' => 200,
        'challenge_won' => 500,  // First place in leaderboard

        // Social & Community
        'achievement_shared' => 15,
        'friend_referred' => 100,
        'workout_buddy_session' => 40,
        'community_post' => 10,
        'helpful_comment' => 5,

        // CBT & Mindset
        'cbt_lesson_completed' => 45,
        'mindset_video_watched' => 20,
        'meditation_completed' => 30,
        'journal_entry' => 25,
        'mood_logged' => 10,

        // Milestones
        'first_milestone' => 100,
        'weight_loss_milestone_5lb' => 150,
        'weight_loss_milestone_10lb' => 300,
        'weight_loss_milestone_25lb' => 750,
        'weight_loss_milestone_50lb' => 1500,
        'muscle_gain_milestone' => 200,
        '100_workouts' => 500,
        '365_day_streak' => 3650,  // 10x daily bonus

        // Consistency Bonuses
        'weekly_active_bonus' => 100,  // Active 5+ days
        'monthly_active_bonus' => 500,  // Active 20+ days
        'perfect_week' => 250,  // All goals met every day
        'comeback_workout' => 50,  // After 7+ days inactive

        // Special Events
        'birthday_workout' => 100,
        'new_year_goal_set' => 75,
        'transformation_complete' => 1000,
    ];

    /**
     * Award body points for an activity
     */
    public function awardPoints(
        int $userId,
        string $reason,
        ?int $customPoints = null,
        ?string $description = null
    ): int {
        $points = $customPoints ?? self::POINTS[$reason] ?? 0;

        if ($points === 0) {
            Log::warning('Attempted to award 0 points', [
                'user_id' => $userId,
                'reason' => $reason
            ]);
            return 0;
        }

        // Record transaction
        DB::table('body_points_transactions')->insert([
            'user_id' => $userId,
            'points' => $points,
            'reason' => $reason,
            'description' => $description ?? $this->getDefaultDescription($reason),
            'created_at' => now()
        ]);

        // Update user's total body points
        DB::table('users')
            ->where('id', $userId)
            ->increment('body_points', $points);

        Log::info('Body points awarded', [
            'user_id' => $userId,
            'points' => $points,
            'reason' => $reason
        ]);

        // Check for point milestone achievements
        $this->checkPointMilestones($userId);

        return $points;
    }

    /**
     * Award points for workout completion
     */
    public function awardWorkoutPoints(int $userId, array $workoutData): int
    {
        $totalPoints = 0;

        // Base workout completion points
        $basePoints = $workoutData['completed_rx'] ?? false
            ? self::POINTS['workout_completed_rx']
            : self::POINTS['workout_completed'];

        $totalPoints += $this->awardPoints($userId,
            $workoutData['completed_rx'] ? 'workout_completed_rx' : 'workout_completed',
            null,
            "Completed workout: {$workoutData['name']}"
        );

        // First workout of the day bonus
        if ($this->isFirstWorkoutToday($userId)) {
            $totalPoints += $this->awardPoints($userId, 'first_workout_of_day');
        }

        // Personal record bonus
        if ($workoutData['is_pr'] ?? false) {
            $totalPoints += $this->awardPoints($userId, 'workout_pr', null,
                "New PR: {$workoutData['pr_description']}"
            );
        }

        // Streak bonus
        $currentStreak = $this->calculateWorkoutStreak($userId);
        if ($currentStreak >= 3) {
            $streakBonus = min($currentStreak * self::POINTS['workout_streak_bonus'], 500);
            $totalPoints += $this->awardPoints($userId, 'workout_streak_bonus', $streakBonus,
                "Workout streak: {$currentStreak} days"
            );
        }

        return $totalPoints;
    }

    /**
     * Award points for nutrition activities
     */
    public function awardNutritionPoints(int $userId, string $activityType, array $data = []): int
    {
        $pointsAwarded = 0;

        switch ($activityType) {
            case 'meal_logged':
                $pointsAwarded = $this->awardPoints($userId, 'meal_logged', null,
                    "Logged {$data['meal_type']}: {$data['calories']} cal"
                );

                // Check if daily nutrition goal met
                if ($this->isDailyNutritionGoalMet($userId)) {
                    $pointsAwarded += $this->awardPoints($userId, 'daily_nutrition_goal_met');
                }
                break;

            case 'water_logged':
                if ($this->isWaterGoalMet($userId)) {
                    $pointsAwarded = $this->awardPoints($userId, 'water_goal_met');
                }
                break;

            case 'meal_prep':
                $pointsAwarded = $this->awardPoints($userId, 'meal_prep_logged', null,
                    "Meal prep completed: {$data['meals_prepared']} meals"
                );
                break;
        }

        // Check weekly nutrition compliance
        if ($this->isWeeklyNutritionCompliant($userId)) {
            $pointsAwarded += $this->awardPoints($userId, 'weekly_nutrition_compliance');
        }

        return $pointsAwarded;
    }

    /**
     * Award points for body measurements and weigh-ins
     */
    public function awardMeasurementPoints(int $userId, string $measurementType, array $data = []): int
    {
        $pointsAwarded = 0;

        switch ($measurementType) {
            case 'weight':
                $pointsAwarded = $this->awardPoints($userId, 'weight_logged', null,
                    "Weight logged: {$data['weight']} {$data['unit']}"
                );

                // Check for weekly weigh-in streak
                if ($this->hasWeeklyWeighInStreak($userId)) {
                    $pointsAwarded += $this->awardPoints($userId, 'weekly_weigh_in_streak');
                }

                // Check for weight loss milestones
                $pointsAwarded += $this->checkWeightLossMilestones($userId, $data);
                break;

            case 'body_measurements':
                $pointsAwarded = $this->awardPoints($userId, 'body_measurement_logged');
                break;

            case 'progress_photo':
                $pointsAwarded = $this->awardPoints($userId, 'progress_photo_uploaded');
                break;
        }

        return $pointsAwarded;
    }

    /**
     * Award points for scheduling activities
     */
    public function awardSchedulingPoints(int $userId, string $activityType, array $data = []): int
    {
        $pointsAwarded = 0;

        switch ($activityType) {
            case 'workout_scheduled':
                $pointsAwarded = $this->awardPoints($userId, 'workout_scheduled', null,
                    "Scheduled: {$data['workout_name']}"
                );
                break;

            case 'meal_plan_created':
                $pointsAwarded = $this->awardPoints($userId, 'meal_plan_created');
                break;

            case 'appointment_attended':
                $pointsAwarded = $this->awardPoints($userId, 'appointment_attended', null,
                    "Attended: {$data['appointment_type']}"
                );
                break;

            case 'weekly_plan_completed':
                $pointsAwarded = $this->awardPoints($userId, 'weekly_plan_completed');
                break;
        }

        return $pointsAwarded;
    }

    /**
     * Award points for challenge activities
     */
    public function awardChallengePoints(int $userId, string $activityType, array $data = []): int
    {
        $pointsAwarded = 0;

        switch ($activityType) {
            case 'joined':
                $pointsAwarded = $this->awardPoints($userId, 'challenge_joined', null,
                    "Joined challenge: {$data['challenge_name']}"
                );
                break;

            case 'daily_completed':
                $pointsAwarded = $this->awardPoints($userId, 'daily_challenge_completed', null,
                    "Day {$data['day_number']} completed"
                );
                break;

            case 'milestone':
                $pointsAwarded = $this->awardPoints($userId, 'challenge_milestone', null,
                    $data['milestone_description']
                );
                break;

            case 'completed':
                $pointsAwarded = $this->awardPoints($userId, 'challenge_completed', null,
                    "Completed: {$data['challenge_name']}"
                );
                break;

            case 'won':
                $pointsAwarded = $this->awardPoints($userId, 'challenge_won', null,
                    "1st place: {$data['challenge_name']}"
                );
                break;
        }

        return $pointsAwarded;
    }

    /**
     * Award points for CBT and mindset activities
     */
    public function awardMindsetPoints(int $userId, string $activityType, array $data = []): int
    {
        $pointsAwarded = 0;

        switch ($activityType) {
            case 'cbt_lesson':
                $pointsAwarded = $this->awardPoints($userId, 'cbt_lesson_completed', null,
                    "Completed lesson: {$data['lesson_title']}"
                );
                break;

            case 'mindset_video':
                $pointsAwarded = $this->awardPoints($userId, 'mindset_video_watched', null,
                    "Watched: {$data['video_title']}"
                );
                break;

            case 'meditation':
                $pointsAwarded = $this->awardPoints($userId, 'meditation_completed', null,
                    "{$data['duration']} min meditation"
                );
                break;

            case 'journal':
                $pointsAwarded = $this->awardPoints($userId, 'journal_entry');
                break;

            case 'mood':
                $pointsAwarded = $this->awardPoints($userId, 'mood_logged');
                break;
        }

        return $pointsAwarded;
    }

    /**
     * Check for weight loss milestones
     */
    private function checkWeightLossMilestones(int $userId, array $currentData): int
    {
        $pointsAwarded = 0;

        $startWeight = DB::table('body_measurements')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->value('weight');

        if (!$startWeight) {
            return 0;
        }

        $currentWeight = $currentData['weight'];
        $weightLost = $startWeight - $currentWeight;

        $milestones = [
            5 => 'weight_loss_milestone_5lb',
            10 => 'weight_loss_milestone_10lb',
            25 => 'weight_loss_milestone_25lb',
            50 => 'weight_loss_milestone_50lb',
        ];

        foreach ($milestones as $lbs => $reason) {
            if ($weightLost >= $lbs) {
                // Check if this milestone was already awarded
                $exists = DB::table('body_points_transactions')
                    ->where('user_id', $userId)
                    ->where('reason', $reason)
                    ->exists();

                if (!$exists) {
                    $pointsAwarded += $this->awardPoints($userId, $reason, null,
                        "Lost {$lbs} lbs! Starting: {$startWeight} → Current: {$currentWeight}"
                    );
                }
            }
        }

        return $pointsAwarded;
    }

    /**
     * Check for point accumulation milestones
     */
    private function checkPointMilestones(int $userId): void
    {
        $totalPoints = DB::table('users')->where('id', $userId)->value('body_points');

        $milestones = [
            1000 => 'First 1,000 Points',
            5000 => 'Bronze Level: 5,000 Points',
            10000 => 'Silver Level: 10,000 Points',
            25000 => 'Gold Level: 25,000 Points',
            50000 => 'Platinum Level: 50,000 Points',
            100000 => 'Diamond Level: 100,000 Points',
        ];

        foreach ($milestones as $points => $name) {
            if ($totalPoints >= $points) {
                $this->checkAndUnlockAchievement($userId, "points_{$points}", $name);
            }
        }
    }

    /**
     * Check and unlock achievement
     */
    private function checkAndUnlockAchievement(int $userId, string $code, string $name): void
    {
        $achievement = DB::table('achievements')->where('code', $code)->first();

        if (!$achievement) {
            return;
        }

        $exists = DB::table('user_achievements')
            ->where('user_id', $userId)
            ->where('achievement_id', $achievement->id)
            ->exists();

        if (!$exists) {
            DB::table('user_achievements')->insert([
                'user_id' => $userId,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
                'created_at' => now()
            ]);

            Log::info('Achievement unlocked', [
                'user_id' => $userId,
                'achievement' => $code
            ]);
        }
    }

    /**
     * Helper methods for streak and goal checking
     */
    private function isFirstWorkoutToday(int $userId): bool
    {
        $count = DB::table('workout_logs')
            ->where('user_id', $userId)
            ->whereDate('completed_at', today())
            ->count();

        return $count === 1;
    }

    private function isDailyNutritionGoalMet(int $userId): bool
    {
        // Check if user logged all required meals today
        $mealsLogged = DB::table('meal_logs')
            ->where('user_id', $userId)
            ->whereDate('meal_time', today())
            ->count();

        return $mealsLogged >= 3; // At least 3 meals
    }

    private function isWaterGoalMet(int $userId): bool
    {
        $waterConsumed = DB::table('water_logs')
            ->where('user_id', $userId)
            ->whereDate('logged_at', today())
            ->sum('ounces');

        return $waterConsumed >= 64; // 64 oz daily goal
    }

    private function isWeeklyNutritionCompliant(int $userId): bool
    {
        $compliantDays = DB::table('meal_logs')
            ->where('user_id', $userId)
            ->whereBetween('meal_time', [now()->startOfWeek(), now()->endOfWeek()])
            ->select(DB::raw('DATE(meal_time) as date'))
            ->groupBy(DB::raw('DATE(meal_time)'))
            ->havingRaw('COUNT(*) >= 3')
            ->get()
            ->count();

        return $compliantDays >= 6; // 6 out of 7 days
    }

    private function hasWeeklyWeighInStreak(int $userId): bool
    {
        $weeksWithWeighIns = DB::table('body_measurements')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subWeeks(4))
            ->select(DB::raw('WEEK(created_at) as week'))
            ->distinct()
            ->count();

        return $weeksWithWeighIns >= 4; // 4 consecutive weeks
    }

    private function calculateWorkoutStreak(int $userId): int
    {
        $logs = DB::table('workout_logs')
            ->where('user_id', $userId)
            ->where('completed', true)
            ->orderBy('completed_at', 'desc')
            ->pluck('completed_at')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->unique()
            ->values()
            ->toArray();

        return $this->calculateStreakFromDates($logs);
    }

    private function calculateStreakFromDates(array $dates): int
    {
        if (empty($dates)) {
            return 0;
        }

        $streak = 0;
        $currentDate = Carbon::now()->startOfDay();

        foreach ($dates as $date) {
            $activityDate = Carbon::parse($date)->startOfDay();

            if ($activityDate->eq($currentDate)) {
                $streak++;
                $currentDate->subDay();
            } elseif ($activityDate->lt($currentDate)) {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get default description for a reason
     */
    private function getDefaultDescription(string $reason): string
    {
        $descriptions = [
            'workout_completed' => 'Completed a workout',
            'workout_completed_rx' => 'Completed workout as prescribed (RX)',
            'first_workout_of_day' => 'First workout of the day bonus',
            'workout_pr' => 'Personal record achieved',
            'meal_logged' => 'Logged a meal',
            'weight_logged' => 'Logged weight',
            'progress_photo_uploaded' => 'Uploaded progress photo',
            'challenge_completed' => 'Completed a challenge',
            'cbt_lesson_completed' => 'Completed CBT lesson',
        ];

        return $descriptions[$reason] ?? ucwords(str_replace('_', ' ', $reason));
    }

    /**
     * Get user's body points balance
     */
    public function getBalance(int $userId): int
    {
        return DB::table('users')->where('id', $userId)->value('body_points') ?? 0;
    }

    /**
     * Get user's points transaction history
     */
    public function getTransactionHistory(int $userId, int $limit = 50): array
    {
        return DB::table('body_points_transactions')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(int $limit = 100, ?int $organizationId = null): array
    {
        $query = DB::table('users')
            ->select('id', 'name', 'email', 'body_points', 'avatar')
            ->where('body_points', '>', 0)
            ->orderBy('body_points', 'desc')
            ->limit($limit);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->get()->toArray();
    }
}
