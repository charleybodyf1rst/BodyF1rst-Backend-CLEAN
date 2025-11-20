<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Gamification Seeder
 *
 * Seeds achievements and badges based on research from top fitness apps 2025:
 * - Fitbit: 100 badges for various achievements
 * - MyFitnessPal: Milestone badges for consecutive day logging
 * - Nike Run Club: Achievement system driving 67% participation
 * - Workout Quest: RPG-style progression system
 */
class GamificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->seedAchievements();
        $this->seedBadges();
    }

    /**
     * Seed achievements
     */
    private function seedAchievements()
    {
        $achievements = [
            // ===== WORKOUT STREAK ACHIEVEMENTS =====
            [
                'code' => 'workout_streak_3',
                'name' => 'Hat Trick',
                'description' => 'Complete workouts 3 days in a row',
                'category' => 'workout',
                'points_reward' => 100,
                'icon' => 'trophy',
                'requirement_value' => 3,
                'requirement_type' => 'workout_streak',
            ],
            [
                'code' => 'workout_streak_7',
                'name' => 'One Week Warrior',
                'description' => 'Complete workouts 7 days in a row',
                'category' => 'workout',
                'points_reward' => 250,
                'icon' => 'trophy-gold',
                'requirement_value' => 7,
                'requirement_type' => 'workout_streak',
            ],
            [
                'code' => 'workout_streak_30',
                'name' => 'Monthly Momentum',
                'description' => 'Complete workouts 30 days in a row',
                'category' => 'workout',
                'points_reward' => 1000,
                'icon' => 'fire',
                'requirement_value' => 30,
                'requirement_type' => 'workout_streak',
            ],
            [
                'code' => 'workout_streak_100',
                'name' => 'Centurion',
                'description' => 'Complete workouts 100 days in a row',
                'category' => 'workout',
                'points_reward' => 5000,
                'icon' => 'fire-legendary',
                'requirement_value' => 100,
                'requirement_type' => 'workout_streak',
            ],
            [
                'code' => 'workout_streak_365',
                'name' => 'Iron Will',
                'description' => 'Complete workouts 365 days in a row',
                'category' => 'workout',
                'points_reward' => 25000,
                'icon' => 'diamond-fire',
                'requirement_value' => 365,
                'requirement_type' => 'workout_streak',
            ],

            // ===== WORKOUT MILESTONES =====
            [
                'code' => 'first_workout',
                'name' => 'Journey Begins',
                'description' => 'Complete your first workout',
                'category' => 'workout',
                'points_reward' => 100,
                'icon' => 'start',
                'requirement_value' => 1,
                'requirement_type' => 'workout_count',
            ],
            [
                'code' => 'workouts_10',
                'name' => 'Getting Started',
                'description' => 'Complete 10 workouts',
                'category' => 'workout',
                'points_reward' => 200,
                'icon' => 'dumbbell',
                'requirement_value' => 10,
                'requirement_type' => 'workout_count',
            ],
            [
                'code' => 'workouts_50',
                'name' => 'Half Century',
                'description' => 'Complete 50 workouts',
                'category' => 'workout',
                'points_reward' => 500,
                'icon' => 'dumbbell-gold',
                'requirement_value' => 50,
                'requirement_type' => 'workout_count',
            ],
            [
                'code' => 'workouts_100',
                'name' => 'Century Club',
                'description' => 'Complete 100 workouts',
                'category' => 'workout',
                'points_reward' => 1500,
                'icon' => 'medal-gold',
                'requirement_value' => 100,
                'requirement_type' => 'workout_count',
            ],
            [
                'code' => 'workouts_500',
                'name' => 'Elite Athlete',
                'description' => 'Complete 500 workouts',
                'category' => 'workout',
                'points_reward' => 10000,
                'icon' => 'crown',
                'requirement_value' => 500,
                'requirement_type' => 'workout_count',
            ],
            [
                'code' => 'workouts_1000',
                'name' => 'Legend',
                'description' => 'Complete 1000 workouts',
                'category' => 'workout',
                'points_reward' => 50000,
                'icon' => 'crown-diamond',
                'requirement_value' => 1000,
                'requirement_type' => 'workout_count',
            ],

            // ===== NUTRITION ACHIEVEMENTS =====
            [
                'code' => 'nutrition_streak_7',
                'name' => 'Meal Tracker',
                'description' => 'Log meals for 7 days straight',
                'category' => 'nutrition',
                'points_reward' => 200,
                'icon' => 'apple',
                'requirement_value' => 7,
                'requirement_type' => 'nutrition_streak',
            ],
            [
                'code' => 'nutrition_streak_30',
                'name' => 'Nutrition Master',
                'description' => 'Log meals for 30 days straight',
                'category' => 'nutrition',
                'points_reward' => 1000,
                'icon' => 'apple-gold',
                'requirement_value' => 30,
                'requirement_type' => 'nutrition_streak',
            ],
            [
                'code' => 'nutrition_perfect_week',
                'name' => 'Perfect Week',
                'description' => 'Hit all nutrition goals for 7 days',
                'category' => 'nutrition',
                'points_reward' => 500,
                'icon' => 'star',
                'requirement_value' => 7,
                'requirement_type' => 'nutrition_goals_met',
            ],
            [
                'code' => 'water_champion',
                'name' => 'Hydration Champion',
                'description' => 'Meet daily water goal for 30 days',
                'category' => 'nutrition',
                'points_reward' => 750,
                'icon' => 'water',
                'requirement_value' => 30,
                'requirement_type' => 'water_goals_met',
            ],

            // ===== WEIGHT & BODY MEASUREMENT ACHIEVEMENTS =====
            [
                'code' => 'first_weigh_in',
                'name' => 'Starting Line',
                'description' => 'Log your first weigh-in',
                'category' => 'measurement',
                'points_reward' => 50,
                'icon' => 'scale',
                'requirement_value' => 1,
                'requirement_type' => 'weigh_in_count',
            ],
            [
                'code' => 'weight_loss_5lb',
                'name' => '5 Pounds Down',
                'description' => 'Lose 5 pounds from starting weight',
                'category' => 'measurement',
                'points_reward' => 500,
                'icon' => 'target',
                'requirement_value' => 5,
                'requirement_type' => 'weight_loss',
            ],
            [
                'code' => 'weight_loss_10lb',
                'name' => '10 Pounds Down',
                'description' => 'Lose 10 pounds from starting weight',
                'category' => 'measurement',
                'points_reward' => 1200,
                'icon' => 'target-gold',
                'requirement_value' => 10,
                'requirement_type' => 'weight_loss',
            ],
            [
                'code' => 'weight_loss_25lb',
                'name' => 'Quarter Century',
                'description' => 'Lose 25 pounds from starting weight',
                'category' => 'measurement',
                'points_reward' => 3500,
                'icon' => 'trophy-diamond',
                'requirement_value' => 25,
                'requirement_type' => 'weight_loss',
            ],
            [
                'code' => 'weight_loss_50lb',
                'name' => 'Transformation',
                'description' => 'Lose 50 pounds from starting weight',
                'category' => 'measurement',
                'points_reward' => 10000,
                'icon' => 'star-legendary',
                'requirement_value' => 50,
                'requirement_type' => 'weight_loss',
            ],
            [
                'code' => 'weekly_weigh_in_streak_4',
                'name' => 'Consistent Tracker',
                'description' => 'Weigh in weekly for 4 weeks straight',
                'category' => 'measurement',
                'points_reward' => 300,
                'icon' => 'calendar',
                'requirement_value' => 4,
                'requirement_type' => 'weekly_weigh_in_streak',
            ],
            [
                'code' => 'progress_photos_10',
                'name' => 'Visual Journey',
                'description' => 'Upload 10 progress photos',
                'category' => 'measurement',
                'points_reward' => 400,
                'icon' => 'camera',
                'requirement_value' => 10,
                'requirement_type' => 'progress_photo_count',
            ],

            // ===== CHALLENGE ACHIEVEMENTS =====
            [
                'code' => 'challenge_first',
                'name' => 'Challenge Accepted',
                'description' => 'Complete your first challenge',
                'category' => 'challenge',
                'points_reward' => 300,
                'icon' => 'flag',
                'requirement_value' => 1,
                'requirement_type' => 'challenge_count',
            ],
            [
                'code' => 'challenge_5',
                'name' => 'Challenge Crusher',
                'description' => 'Complete 5 challenges',
                'category' => 'challenge',
                'points_reward' => 1000,
                'icon' => 'flag-gold',
                'requirement_value' => 5,
                'requirement_type' => 'challenge_count',
            ],
            [
                'code' => 'challenge_winner',
                'name' => 'Champion',
                'description' => 'Win your first challenge (1st place)',
                'category' => 'challenge',
                'points_reward' => 2000,
                'icon' => 'crown-gold',
                'requirement_value' => 1,
                'requirement_type' => 'challenge_wins',
            ],
            [
                'code' => 'challenge_30_day',
                'name' => '30-Day Warrior',
                'description' => 'Complete a 30-day challenge',
                'category' => 'challenge',
                'points_reward' => 1500,
                'icon' => 'trophy-legendary',
                'requirement_value' => 30,
                'requirement_type' => 'challenge_duration',
            ],

            // ===== CBT & MINDSET ACHIEVEMENTS =====
            [
                'code' => 'cbt_lessons_10',
                'name' => 'Mind & Body',
                'description' => 'Complete 10 CBT lessons',
                'category' => 'cbt',
                'points_reward' => 500,
                'icon' => 'brain',
                'requirement_value' => 10,
                'requirement_type' => 'cbt_lesson_count',
            ],
            [
                'code' => 'meditation_streak_7',
                'name' => 'Zen Master',
                'description' => 'Meditate for 7 days straight',
                'category' => 'cbt',
                'points_reward' => 350,
                'icon' => 'lotus',
                'requirement_value' => 7,
                'requirement_type' => 'meditation_streak',
            ],
            [
                'code' => 'journal_entries_30',
                'name' => 'Reflective Mind',
                'description' => 'Create 30 journal entries',
                'category' => 'cbt',
                'points_reward' => 600,
                'icon' => 'book',
                'requirement_value' => 30,
                'requirement_type' => 'journal_count',
            ],

            // ===== OVERALL ACHIEVEMENTS =====
            [
                'code' => 'overall_streak_7',
                'name' => 'Active Week',
                'description' => 'Be active 7 days in a row (any activity)',
                'category' => 'overall',
                'points_reward' => 300,
                'icon' => 'lightning',
                'requirement_value' => 7,
                'requirement_type' => 'overall_streak',
            ],
            [
                'code' => 'overall_streak_30',
                'name' => 'Unstoppable',
                'description' => 'Be active 30 days in a row',
                'category' => 'overall',
                'points_reward' => 1500,
                'icon' => 'lightning-legendary',
                'requirement_value' => 30,
                'requirement_type' => 'overall_streak',
            ],
            [
                'code' => 'perfect_week',
                'name' => 'Perfect Week',
                'description' => 'Complete all goals for 7 days',
                'category' => 'overall',
                'points_reward' => 1000,
                'icon' => 'perfect-star',
                'requirement_value' => 7,
                'requirement_type' => 'perfect_days',
            ],

            // ===== BODY POINTS MILESTONES =====
            [
                'code' => 'points_1000',
                'name' => '1K Points',
                'description' => 'Earn 1,000 body points',
                'category' => 'points',
                'points_reward' => 0, // No extra points for point milestones
                'icon' => 'coin-bronze',
                'requirement_value' => 1000,
                'requirement_type' => 'total_points',
            ],
            [
                'code' => 'points_5000',
                'name' => 'Bronze Level',
                'description' => 'Earn 5,000 body points',
                'category' => 'points',
                'points_reward' => 0,
                'icon' => 'medal-bronze',
                'requirement_value' => 5000,
                'requirement_type' => 'total_points',
            ],
            [
                'code' => 'points_10000',
                'name' => 'Silver Level',
                'description' => 'Earn 10,000 body points',
                'category' => 'points',
                'points_reward' => 0,
                'icon' => 'medal-silver',
                'requirement_value' => 10000,
                'requirement_type' => 'total_points',
            ],
            [
                'code' => 'points_25000',
                'name' => 'Gold Level',
                'description' => 'Earn 25,000 body points',
                'category' => 'points',
                'points_reward' => 0,
                'icon' => 'medal-gold',
                'requirement_value' => 25000,
                'requirement_type' => 'total_points',
            ],
            [
                'code' => 'points_50000',
                'name' => 'Platinum Level',
                'description' => 'Earn 50,000 body points',
                'category' => 'points',
                'points_reward' => 0,
                'icon' => 'medal-platinum',
                'requirement_value' => 50000,
                'requirement_type' => 'total_points',
            ],
            [
                'code' => 'points_100000',
                'name' => 'Diamond Level',
                'description' => 'Earn 100,000 body points',
                'category' => 'points',
                'points_reward' => 0,
                'icon' => 'medal-diamond',
                'requirement_value' => 100000,
                'requirement_type' => 'total_points',
            ],

            // ===== SOCIAL ACHIEVEMENTS =====
            [
                'code' => 'friend_referred',
                'name' => 'Friend Referrer',
                'description' => 'Refer a friend to the app',
                'category' => 'social',
                'points_reward' => 500,
                'icon' => 'users',
                'requirement_value' => 1,
                'requirement_type' => 'referral_count',
            ],
            [
                'code' => 'achievement_shared_10',
                'name' => 'Social Butterfly',
                'description' => 'Share 10 achievements on social media',
                'category' => 'social',
                'points_reward' => 300,
                'icon' => 'share',
                'requirement_value' => 10,
                'requirement_type' => 'share_count',
            ],
        ];

        foreach ($achievements as $achievement) {
            $achievement['is_active'] = true;
            $achievement['created_at'] = Carbon::now();
            $achievement['updated_at'] = Carbon::now();

            DB::table('achievements')->insert($achievement);
        }

        $this->command->info('✓ Seeded ' . count($achievements) . ' achievements');
    }

    /**
     * Seed badges
     */
    private function seedBadges()
    {
        $badges = [
            // ===== BRONZE TIER BADGES =====
            [
                'code' => 'newcomer',
                'name' => 'Newcomer',
                'description' => 'Welcome to BodyF1rst!',
                'icon' => 'star-bronze',
                'tier' => 'bronze',
                'points_value' => 50,
            ],
            [
                'code' => 'first_week',
                'name' => 'First Week',
                'description' => 'Active for your first week',
                'icon' => 'calendar-bronze',
                'tier' => 'bronze',
                'points_value' => 100,
            ],
            [
                'code' => 'workout_starter',
                'name' => 'Workout Starter',
                'description' => 'Complete 10 workouts',
                'icon' => 'dumbbell-bronze',
                'tier' => 'bronze',
                'points_value' => 150,
            ],
            [
                'code' => 'meal_tracker_bronze',
                'name' => 'Meal Tracker',
                'description' => 'Log meals for 7 days',
                'icon' => 'apple-bronze',
                'tier' => 'bronze',
                'points_value' => 150,
            ],

            // ===== SILVER TIER BADGES =====
            [
                'code' => 'workout_enthusiast',
                'name' => 'Workout Enthusiast',
                'description' => 'Complete 50 workouts',
                'icon' => 'dumbbell-silver',
                'tier' => 'silver',
                'points_value' => 300,
            ],
            [
                'code' => 'nutrition_expert',
                'name' => 'Nutrition Expert',
                'description' => 'Log meals for 30 days',
                'icon' => 'apple-silver',
                'tier' => 'silver',
                'points_value' => 400,
            ],
            [
                'code' => 'streak_keeper_silver',
                'name' => 'Streak Keeper',
                'description' => '30-day activity streak',
                'icon' => 'fire-silver',
                'tier' => 'silver',
                'points_value' => 500,
            ],
            [
                'code' => 'transformation_begun',
                'name' => 'Transformation Begun',
                'description' => 'Lose 10 pounds',
                'icon' => 'target-silver',
                'tier' => 'silver',
                'points_value' => 600,
            ],

            // ===== GOLD TIER BADGES =====
            [
                'code' => 'workout_master',
                'name' => 'Workout Master',
                'description' => 'Complete 100 workouts',
                'icon' => 'dumbbell-gold',
                'tier' => 'gold',
                'points_value' => 1000,
            ],
            [
                'code' => 'nutrition_guru',
                'name' => 'Nutrition Guru',
                'description' => 'Perfect nutrition for 90 days',
                'icon' => 'apple-gold',
                'tier' => 'gold',
                'points_value' => 1200,
            ],
            [
                'code' => 'century_club',
                'name' => 'Century Club',
                'description' => '100-day activity streak',
                'icon' => 'fire-gold',
                'tier' => 'gold',
                'points_value' => 2000,
            ],
            [
                'code' => 'major_transformation',
                'name' => 'Major Transformation',
                'description' => 'Lose 25 pounds',
                'icon' => 'target-gold',
                'tier' => 'gold',
                'points_value' => 2500,
            ],
            [
                'code' => 'challenge_champion',
                'name' => 'Challenge Champion',
                'description' => 'Win 5 challenges',
                'icon' => 'crown-gold',
                'tier' => 'gold',
                'points_value' => 3000,
            ],

            // ===== PLATINUM TIER BADGES =====
            [
                'code' => 'elite_athlete',
                'name' => 'Elite Athlete',
                'description' => 'Complete 500 workouts',
                'icon' => 'dumbbell-platinum',
                'tier' => 'platinum',
                'points_value' => 5000,
            ],
            [
                'code' => 'iron_will',
                'name' => 'Iron Will',
                'description' => '365-day activity streak',
                'icon' => 'fire-platinum',
                'tier' => 'platinum',
                'points_value' => 10000,
            ],
            [
                'code' => 'life_changer',
                'name' => 'Life Changer',
                'description' => 'Lose 50 pounds',
                'icon' => 'star-platinum',
                'tier' => 'platinum',
                'points_value' => 15000,
            ],
            [
                'code' => 'point_millionaire',
                'name' => 'Point Millionaire',
                'description' => 'Earn 100,000 body points',
                'icon' => 'diamond',
                'tier' => 'platinum',
                'points_value' => 0, // Honorary badge
            ],

            // ===== SPECIAL BADGES =====
            [
                'code' => 'early_bird',
                'name' => 'Early Bird',
                'description' => 'Complete 10 morning workouts',
                'icon' => 'sunrise',
                'tier' => 'bronze',
                'points_value' => 200,
            ],
            [
                'code' => 'night_owl',
                'name' => 'Night Owl',
                'description' => 'Complete 10 evening workouts',
                'icon' => 'moon',
                'tier' => 'bronze',
                'points_value' => 200,
            ],
            [
                'code' => 'weekend_warrior',
                'name' => 'Weekend Warrior',
                'description' => 'Active every weekend for 4 weeks',
                'icon' => 'weekend',
                'tier' => 'silver',
                'points_value' => 300,
            ],
            [
                'code' => 'comeback_kid',
                'name' => 'Comeback Kid',
                'description' => 'Return after 30+ days inactive',
                'icon' => 'comeback',
                'tier' => 'bronze',
                'points_value' => 250,
            ],
            [
                'code' => 'personal_best',
                'name' => 'Personal Best',
                'description' => 'Set 10 personal records',
                'icon' => 'record',
                'tier' => 'gold',
                'points_value' => 1000,
            ],
            [
                'code' => 'team_player',
                'name' => 'Team Player',
                'description' => 'Complete 10 group workouts',
                'icon' => 'team',
                'tier' => 'silver',
                'points_value' => 500,
            ],
            [
                'code' => 'birthday_bonus',
                'name' => 'Birthday Bonus',
                'description' => 'Workout on your birthday',
                'icon' => 'cake',
                'tier' => 'bronze',
                'points_value' => 100,
            ],
        ];

        foreach ($badges as $badge) {
            $badge['is_active'] = true;
            $badge['created_at'] = Carbon::now();
            $badge['updated_at'] = Carbon::now();

            DB::table('badges')->insert($badge);
        }

        $this->command->info('✓ Seeded ' . count($badges) . ' badges');
    }
}
