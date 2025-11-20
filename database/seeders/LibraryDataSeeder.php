<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LibraryDataSeeder extends Seeder
{
    /**
     * Seed the library tables with 40 workouts, 10 nutrition plans, and 10 challenges
     */
    public function run()
    {
        echo "Seeding BodyF1rst Library Data...\n\n";

        $adminId = 1; // Admin creator ID
        $now = Carbon::now();

        // Seed Workout Templates (40)
        $this->seedWorkoutLibrary($adminId, $now);

        // Seed Nutrition Plans (10)
        $this->seedNutritionLibrary($adminId, $now);

        // Seed Challenges (10)
        $this->seedChallengeLibrary($adminId, $now);

        echo "\nLibrary data seeded successfully!\n";
        echo "   40 Workout Templates\n";
        echo "   10 Nutrition Plans\n";
        echo "   10 Challenges\n\n";
    }

    private function seedWorkoutLibrary($adminId, $now)
    {
        echo "  Creating 40 Workout Templates...\n";

        $workouts = [
            // Strength Training (10)
            ['name' => 'Full Body Strength Foundation', 'category' => 'strength', 'difficulty_level' => 'beginner', 'goal' => 'strength', 'duration_weeks' => 4, 'sessions_per_week' => 3],
            ['name' => 'Upper Body Power Builder', 'category' => 'strength', 'difficulty_level' => 'intermediate', 'goal' => 'hypertrophy', 'duration_weeks' => 6, 'sessions_per_week' => 4],
            ['name' => 'Lower Body Strength', 'category' => 'strength', 'difficulty_level' => 'intermediate', 'goal' => 'strength', 'duration_weeks' => 6, 'sessions_per_week' => 3],
            ['name' => 'Push Pull Legs Split', 'category' => 'strength', 'difficulty_level' => 'advanced', 'goal' => 'hypertrophy', 'duration_weeks' => 8, 'sessions_per_week' => 6],
            ['name' => 'Powerlifting Basics', 'category' => 'strength', 'difficulty_level' => 'intermediate', 'goal' => 'strength', 'duration_weeks' => 12, 'sessions_per_week' => 4],
            ['name' => 'Olympic Lifting Fundamentals', 'category' => 'strength', 'difficulty_level' => 'advanced', 'goal' => 'strength', 'duration_weeks' => 8, 'sessions_per_week' => 3],
            ['name' => 'Bodyweight Strength Mastery', 'category' => 'strength', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 6, 'sessions_per_week' => 3],
            ['name' => 'Kettlebell Complex Training', 'category' => 'strength', 'difficulty_level' => 'intermediate', 'goal' => 'strength', 'duration_weeks' => 6, 'sessions_per_week' => 3],
            ['name' => 'Total Body Conditioning', 'category' => 'strength', 'difficulty_level' => 'intermediate', 'goal' => 'general_fitness', 'duration_weeks' => 8, 'sessions_per_week' => 4],
            ['name' => 'Athletic Performance Training', 'category' => 'strength', 'difficulty_level' => 'advanced', 'goal' => 'strength', 'duration_weeks' => 12, 'sessions_per_week' => 5],

            // HIIT & Cardio (10)
            ['name' => 'HIIT Fat Burner', 'category' => 'hiit', 'difficulty_level' => 'intermediate', 'goal' => 'endurance', 'duration_weeks' => 4, 'sessions_per_week' => 3],
            ['name' => 'Tabata Training Protocol', 'category' => 'hiit', 'difficulty_level' => 'advanced', 'goal' => 'endurance', 'duration_weeks' => 4, 'sessions_per_week' => 4],
            ['name' => 'Cardio Kickstart', 'category' => 'cardio', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 4, 'sessions_per_week' => 3],
            ['name' => 'Endurance Builder', 'category' => 'cardio', 'difficulty_level' => 'intermediate', 'goal' => 'endurance', 'duration_weeks' => 8, 'sessions_per_week' => 4],
            ['name' => 'Sprint Interval Training', 'category' => 'hiit', 'difficulty_level' => 'advanced', 'goal' => 'endurance', 'duration_weeks' => 6, 'sessions_per_week' => 3],
            ['name' => 'Metabolic Conditioning', 'category' => 'hiit', 'difficulty_level' => 'intermediate', 'goal' => 'endurance', 'duration_weeks' => 6, 'sessions_per_week' => 4],
            ['name' => 'Fat Loss Accelerator', 'category' => 'hiit', 'difficulty_level' => 'intermediate', 'goal' => 'endurance', 'duration_weeks' => 6, 'sessions_per_week' => 5],
            ['name' => 'Boxing HIIT Workout', 'category' => 'hiit', 'difficulty_level' => 'intermediate', 'goal' => 'endurance', 'duration_weeks' => 4, 'sessions_per_week' => 3],
            ['name' => 'Rowing Machine Intervals', 'category' => 'cardio', 'difficulty_level' => 'beginner', 'goal' => 'endurance', 'duration_weeks' => 4, 'sessions_per_week' => 3],
            ['name' => 'Jump Rope Cardio Blast', 'category' => 'hiit', 'difficulty_level' => 'beginner', 'goal' => 'endurance', 'duration_weeks' => 4, 'sessions_per_week' => 3],

            // CrossFit & Functional (10)
            ['name' => 'CrossFit WOD Foundations', 'category' => 'crossfit', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 6, 'sessions_per_week' => 3],
            ['name' => 'AMRAP Madness', 'category' => 'crossfit', 'difficulty_level' => 'intermediate', 'goal' => 'endurance', 'duration_weeks' => 4, 'sessions_per_week' => 4],
            ['name' => 'EMOM Training Protocol', 'category' => 'crossfit', 'difficulty_level' => 'intermediate', 'goal' => 'general_fitness', 'duration_weeks' => 4, 'sessions_per_week' => 3],
            ['name' => 'Functional Fitness', 'category' => 'functional', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 8, 'sessions_per_week' => 3],
            ['name' => 'Warrior Workout', 'category' => 'crossfit', 'difficulty_level' => 'advanced', 'goal' => 'strength', 'duration_weeks' => 8, 'sessions_per_week' => 5],
            ['name' => 'Mobility & Movement', 'category' => 'functional', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 4, 'sessions_per_week' => 3],
            ['name' => 'Core Strength Builder', 'category' => 'functional', 'difficulty_level' => 'beginner', 'goal' => 'strength', 'duration_weeks' => 6, 'sessions_per_week' => 3],
            ['name' => 'Battle Ropes & Sleds', 'category' => 'functional', 'difficulty_level' => 'intermediate', 'goal' => 'endurance', 'duration_weeks' => 6, 'sessions_per_week' => 3],
            ['name' => 'TRX Suspension Training', 'category' => 'functional', 'difficulty_level' => 'intermediate', 'goal' => 'strength', 'duration_weeks' => 6, 'sessions_per_week' => 3],
            ['name' => 'Sandbag Training', 'category' => 'functional', 'difficulty_level' => 'advanced', 'goal' => 'strength', 'duration_weeks' => 8, 'sessions_per_week' => 3],

            // Specialized & Recovery (10)
            ['name' => 'Yoga for Athletes', 'category' => 'yoga', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 4, 'sessions_per_week' => 3],
            ['name' => 'Pilates Core Strength', 'category' => 'pilates', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 6, 'sessions_per_week' => 3],
            ['name' => 'Active Recovery Protocol', 'category' => 'recovery', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 4, 'sessions_per_week' => 2],
            ['name' => 'Swimming Workout Plan', 'category' => 'cardio', 'difficulty_level' => 'intermediate', 'goal' => 'endurance', 'duration_weeks' => 8, 'sessions_per_week' => 3],
            ['name' => 'Cycling Endurance Builder', 'category' => 'cardio', 'difficulty_level' => 'intermediate', 'goal' => 'endurance', 'duration_weeks' => 8, 'sessions_per_week' => 4],
            ['name' => 'Running Base Building', 'category' => 'cardio', 'difficulty_level' => 'beginner', 'goal' => 'endurance', 'duration_weeks' => 12, 'sessions_per_week' => 3],
            ['name' => 'Marathon Preparation', 'category' => 'cardio', 'difficulty_level' => 'advanced', 'goal' => 'endurance', 'duration_weeks' => 16, 'sessions_per_week' => 5],
            ['name' => 'Triathlon Training', 'category' => 'cardio', 'difficulty_level' => 'advanced', 'goal' => 'endurance', 'duration_weeks' => 16, 'sessions_per_week' => 6],
            ['name' => 'Senior Fitness Program', 'category' => 'functional', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 8, 'sessions_per_week' => 3],
            ['name' => 'Pregnancy Safe Workouts', 'category' => 'functional', 'difficulty_level' => 'beginner', 'goal' => 'general_fitness', 'duration_weeks' => 12, 'sessions_per_week' => 3],
        ];

        foreach ($workouts as $index => $workout) {
            DB::table('workout_library')->insert([
                'created_by_admin_id' => $adminId,
                'name' => $workout['name'],
                'description' => "A comprehensive {$workout['difficulty_level']} level {$workout['category']} program designed to help you achieve {$workout['goal']} goals. This {$workout['duration_weeks']}-week program includes {$workout['sessions_per_week']} sessions per week with progressive overload and detailed exercise breakdowns.",
                'category' => $workout['category'],
                'difficulty_level' => $workout['difficulty_level'],
                'goal' => $workout['goal'],
                'duration_weeks' => $workout['duration_weeks'],
                'sessions_per_week' => $workout['sessions_per_week'],
                'exercises' => json_encode([]),
                'tags' => json_encode([$workout['category'], $workout['difficulty_level'], $workout['goal']]),
                'thumbnail_url' => null,
                'is_featured' => $index < 5, // First 5 are featured
                'clone_count' => 0,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "    40 workout templates created\n";
    }

    private function seedNutritionLibrary($adminId, $now)
    {
        echo "  Creating 10 Nutrition Plans...\n";

        $plans = [
            ['name' => 'Weight Loss Jumpstart', 'goal_type' => 'weight_loss', 'duration_days' => 30, 'calories' => 1500, 'protein' => 120, 'carbs' => 150, 'fat' => 50],
            ['name' => 'Muscle Gain Bulking Plan', 'goal_type' => 'muscle_gain', 'duration_days' => 60, 'calories' => 2800, 'protein' => 210, 'carbs' => 350, 'fat' => 70],
            ['name' => 'Balanced Maintenance', 'goal_type' => 'maintenance', 'duration_days' => 30, 'calories' => 2000, 'protein' => 150, 'carbs' => 225, 'fat' => 65],
            ['name' => 'Keto Fat Loss', 'goal_type' => 'weight_loss', 'duration_days' => 30, 'calories' => 1800, 'protein' => 130, 'carbs' => 50, 'fat' => 120],
            ['name' => 'Vegan Athlete Plan', 'goal_type' => 'maintenance', 'duration_days' => 30, 'calories' => 2200, 'protein' => 120, 'carbs' => 280, 'fat' => 70],
            ['name' => 'Mediterranean Diet', 'goal_type' => 'maintenance', 'duration_days' => 30, 'calories' => 2100, 'protein' => 140, 'carbs' => 230, 'fat' => 75],
            ['name' => 'High Protein Cutting', 'goal_type' => 'weight_loss', 'duration_days' => 45, 'calories' => 1700, 'protein' => 180, 'carbs' => 120, 'fat' => 55],
            ['name' => 'Intermittent Fasting 16:8', 'goal_type' => 'weight_loss', 'duration_days' => 30, 'calories' => 1900, 'protein' => 140, 'carbs' => 180, 'fat' => 65],
            ['name' => 'Clean Eating Lifestyle', 'goal_type' => 'maintenance', 'duration_days' => 30, 'calories' => 2000, 'protein' => 150, 'carbs' => 220, 'fat' => 65],
            ['name' => 'Performance Fuel Plan', 'goal_type' => 'muscle_gain', 'duration_days' => 45, 'calories' => 2600, 'protein' => 190, 'carbs' => 320, 'fat' => 70],
        ];

        foreach ($plans as $index => $plan) {
            DB::table('nutrition_plan_library')->insert([
                'created_by_admin_id' => $adminId,
                'name' => $plan['name'],
                'description' => "A scientifically-designed {$plan['duration_days']}-day nutrition plan for {$plan['goal_type']}. Includes detailed meal plans, macronutrient tracking, and shopping lists to help you achieve your goals.",
                'duration_days' => $plan['duration_days'],
                'daily_calories' => $plan['calories'],
                'daily_protein_g' => $plan['protein'],
                'daily_carbs_g' => $plan['carbs'],
                'daily_fat_g' => $plan['fat'],
                'goal_type' => $plan['goal_type'],
                'activity_level' => 'moderate',
                'meals' => json_encode([]),
                'tags' => json_encode([$plan['goal_type'], 'meal_plan']),
                'thumbnail_url' => null,
                'is_featured' => $index < 3,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "    10 nutrition plans created\n";
    }

    private function seedChallengeLibrary($adminId, $now)
    {
        echo "  Creating 10 Challenges...\n";

        $challenges = [
            ['name' => '30-Day Fitness Challenge', 'type' => 'fitness', 'days' => 30],
            ['name' => '7-Day Water Intake Challenge', 'type' => 'hydration', 'days' => 7],
            ['name' => '14-Day Plank Challenge', 'type' => 'core', 'days' => 14],
            ['name' => '21-Day Healthy Eating Challenge', 'type' => 'nutrition', 'days' => 21],
            ['name' => '30-Day Push-Up Challenge', 'type' => 'strength', 'days' => 30],
            ['name' => '60-Day Transformation Challenge', 'type' => 'transformation', 'days' => 60],
            ['name' => '10K Steps Daily Challenge', 'type' => 'cardio', 'days' => 30],
            ['name' => '14-Day No Sugar Challenge', 'type' => 'nutrition', 'days' => 14],
            ['name' => '30-Day Yoga Challenge', 'type' => 'flexibility', 'days' => 30],
            ['name' => '90-Day Body Recomposition', 'type' => 'transformation', 'days' => 90],
        ];

        foreach ($challenges as $index => $challenge) {
            DB::table('challenge_library')->insert([
                'created_by_admin_id' => $adminId,
                'name' => $challenge['name'],
                'description' => "Join our {$challenge['days']}-day {$challenge['type']} challenge! This structured program includes daily tasks, progress tracking, and community support to help you build lasting habits and achieve your fitness goals.",
                'challenge_type' => $challenge['type'],
                'duration_days' => $challenge['days'],
                'daily_tasks' => json_encode([]),
                'rules' => json_encode([
                    'Complete all daily tasks',
                    'Track your progress',
                    'Stay committed for the full duration',
                    'Support fellow participants'
                ]),
                'rewards' => json_encode([
                    'Achievement badge',
                    'Progress certificate',
                    'Community recognition'
                ]),
                'thumbnail_url' => null,
                'is_featured' => $index < 3,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "    10 challenges created\n";
    }
}
