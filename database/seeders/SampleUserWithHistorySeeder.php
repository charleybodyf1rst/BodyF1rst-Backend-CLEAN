<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Coach;
use App\Models\Organization;

/**
 * WARNING: FOR DEMO AND PRESENTATION PURPOSES ONLY
 * DO NOT run this seeder in production environments
 * Creates sample users with 3 months of historical data for demos
 */
class SampleUserWithHistorySeeder extends Seeder
{
    /**
     * Seed a complete demo user with 3 months of historical data
     * for graphs, analytics, and meeting demonstrations
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('Skipping SampleUserWithHistorySeeder in production environment');
            return;
        }
        echo "🚀 Creating sample data for demo...\n\n";

        // 1. CREATE ORGANIZATION
        echo "📊 Creating demo organization...\n";
        $organization = Organization::updateOrCreate(
            ['name' => 'BodyF1rst Demo Corporation'],
            [
                'contact_person' => 'John Demo Manager',
                'email' => 'demo-org@bodyf1rst.com',
                'phone' => '+1-555-DEMO-123',
                'address' => '123 Fitness Avenue, Demo City, CA 90210',
                'status' => 'active',
                'contract_start_date' => now()->subMonths(6)->toDateString(),
                'contract_end_date' => now()->addMonths(6)->toDateString(),
                'created_at' => now()->subMonths(6),
            ]
        );
        echo "   ✅ Organization created: {$organization->name} (ID: {$organization->id})\n\n";

        // 2. CREATE COACH
        echo "👨‍🏫 Creating demo coach...\n";
        $coach = Coach::updateOrCreate(
            ['email' => 'demo-coach@bodyf1rst.com'],
            [
                'name' => 'Sarah Johnson',
                'phone' => '+1-555-COACH-01',
                'password' => Hash::make('coach123'),
                'is_active' => 1,
                'specialization' => 'Strength & Nutrition',
                'certification' => 'NASM-CPT, Precision Nutrition L1',
                'bio' => 'Certified personal trainer with 8+ years experience in strength training and nutrition coaching.',
                'created_at' => now()->subMonths(6),
            ]
        );
        echo "   ✅ Coach created: {$coach->name} ({$coach->email})\n";
        echo "   🔑 Password: coach123\n\n";

        // Assign coach to organization
        DB::table('coach_organizations')->updateOrInsert(
            ['coach_id' => $coach->id, 'organization_id' => $organization->id],
            ['created_at' => now()->subMonths(6), 'updated_at' => now()]
        );

        // 3. CREATE DEMO CLIENT WITH 3 MONTHS OF DATA
        echo "👤 Creating demo client with 3 months of data...\n";
        $client = User::updateOrCreate(
            ['email' => 'demo-client@bodyf1rst.com'],
            [
                'name' => 'Michael Thompson',
                'password' => Hash::make('client123'),
                'phone' => '+1-555-CLIENT-01',
                'is_active' => 1,
                'email_verified_at' => now()->subMonths(3),

                // Profile data
                'gender' => 'male',
                'age' => 32,
                'height' => 178, // cm
                'weight' => 85, // kg (starting weight 3 months ago was 95kg)
                'target_weight' => 80,
                'bmi' => 26.8,
                'bmr' => 1850,

                // Initial body composition (3 months ago)
                'body_fat_percentage' => 22, // Started at 28%
                'muscle_mass' => 60, // Started at 56kg

                // Goals
                'fitness_goal' => 'weight_loss',
                'activity_level' => 'moderately_active',
                'medical_conditions' => json_encode([]),
                'dietary_restrictions' => json_encode(['gluten_free']),

                // Gamification
                'body_points' => 2450,
                'current_streak' => 12,
                'longest_streak' => 21,
                'level' => 8,

                // Organization
                'organization_id' => $organization->id,
                'created_at' => now()->subMonths(3),
            ]
        );
        echo "   ✅ Client created: {$client->name} ({$client->email})\n";
        echo "   🔑 Password: client123\n";
        echo "   📊 Initial stats: 95kg, 28% BF → Current: 85kg, 22% BF (10kg lost!)\n\n";

        // Assign coach to client
        DB::table('coach_users')->updateOrInsert(
            ['coach_id' => $coach->id, 'user_id' => $client->id],
            ['assigned_at' => now()->subMonths(3), 'created_at' => now()->subMonths(3)]
        );

        // 4. CREATE BODY MEASUREMENT HISTORY (Weekly for 3 months = 12 data points)
        echo "📈 Creating body measurement history (12 weeks)...\n";
        $startWeight = 95;
        $endWeight = 85;
        $startBF = 28;
        $endBF = 22;
        $startMuscle = 56;
        $endMuscle = 60;

        for ($week = 0; $week < 12; $week++) {
            $progress = $week / 11; // 0 to 1
            $date = now()->subWeeks(11 - $week);

            $weight = $startWeight - (($startWeight - $endWeight) * $progress);
            $bodyFat = $startBF - (($startBF - $endBF) * $progress);
            $muscle = $startMuscle + (($endMuscle - $startMuscle) * $progress);

            DB::table('body_measurements')->insert([
                'user_id' => $client->id,
                'weight' => round($weight, 1),
                'body_fat_percentage' => round($bodyFat, 1),
                'muscle_mass' => round($muscle, 1),
                'chest' => 98 - ($progress * 5),
                'waist' => 92 - ($progress * 8),
                'hips' => 102 - ($progress * 4),
                'arms' => 35 + ($progress * 2),
                'thighs' => 58 - ($progress * 3),
                'measured_at' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
        echo "   ✅ 12 weekly measurements created\n\n";

        // 5. CREATE WORKOUT PLAN
        echo "💪 Creating 12-week workout plan...\n";
        $workoutPlan = DB::table('plans')->insertGetId([
            'name' => 'Fat Loss Transformation - 12 Week Program',
            'description' => 'Comprehensive strength training and conditioning program for fat loss',
            'duration' => 12,
            'difficulty' => 'intermediate',
            'goal' => 'weight_loss',
            'created_by' => $coach->id,
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subMonths(3),
        ]);

        // Create 4 different workouts (rotate weekly)
        $workouts = [
            [
                'name' => 'Full Body Strength A',
                'description' => 'Compound movements focusing on major muscle groups',
                'duration' => 45,
                'difficulty' => 'intermediate',
                'category' => 'strength',
            ],
            [
                'name' => 'Upper Body Push',
                'description' => 'Chest, shoulders, and triceps workout',
                'duration' => 40,
                'difficulty' => 'intermediate',
                'category' => 'strength',
            ],
            [
                'name' => 'Lower Body & Core',
                'description' => 'Legs, glutes, and core strengthening',
                'duration' => 50,
                'difficulty' => 'intermediate',
                'category' => 'strength',
            ],
            [
                'name' => 'HIIT Cardio & Conditioning',
                'description' => 'High intensity interval training for fat burning',
                'duration' => 30,
                'difficulty' => 'advanced',
                'category' => 'cardio',
            ],
        ];

        $workoutIds = [];
        foreach ($workouts as $workout) {
            $workoutIds[] = DB::table('workouts')->insertGetId(array_merge($workout, [
                'created_by' => $coach->id,
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(3),
            ]));
        }

        // Link workouts to plan
        foreach ($workoutIds as $index => $workoutId) {
            DB::table('plan_workouts')->insert([
                'plan_id' => $workoutPlan,
                'workout_id' => $workoutId,
                'week_number' => 1,
                'day_number' => $index + 1,
                'created_at' => now()->subMonths(3),
            ]);
        }

        // Assign plan to client
        DB::table('assign_plans')->insert([
            'user_id' => $client->id,
            'plan_id' => $workoutPlan,
            'assigned_by' => $coach->id,
            'assigned_at' => now()->subMonths(3),
            'status' => 'active',
            'created_at' => now()->subMonths(3),
        ]);
        echo "   ✅ Workout plan created with 4 workouts\n\n";

        // 6. CREATE 3 MONTHS OF COMPLETED WORKOUTS (4x/week = 48 workouts)
        echo "🏋️ Creating 3 months of workout history (48 workouts)...\n";
        $completedCount = 0;

        for ($week = 0; $week < 12; $week++) {
            // 4 workouts per week (Mon, Wed, Fri, Sat)
            $days = [1, 3, 5, 6]; // Monday, Wednesday, Friday, Saturday

            foreach ($days as $dayIndex => $dayOfWeek) {
                $workoutDate = now()->subWeeks(11 - $week)->startOfWeek()->addDays($dayOfWeek - 1);

                // Skip future dates
                if ($workoutDate->isFuture()) continue;

                $workoutId = $workoutIds[$dayIndex];
                $duration = [42, 38, 48, 28][$dayIndex]; // Slight variation from planned

                DB::table('user_completed_workouts')->insert([
                    'user_id' => $client->id,
                    'workout_id' => $workoutId,
                    'completed_at' => $workoutDate,
                    'duration_minutes' => $duration + rand(-3, 3),
                    'calories_burned' => [420, 350, 480, 520][$dayIndex] + rand(-20, 20),
                    'average_heart_rate' => 145 + rand(-10, 10),
                    'rating' => rand(4, 5), // 4-5 stars
                    'notes' => $week % 3 == 0 ? 'Felt great! Increasing weights next week.' : null,
                    'created_at' => $workoutDate,
                    'updated_at' => $workoutDate,
                ]);

                $completedCount++;
            }
        }
        echo "   ✅ {$completedCount} completed workouts logged\n\n";

        // 7. CREATE NUTRITION PLAN
        echo "🥗 Creating nutrition plan...\n";
        $nutritionPlan = DB::table('nutrition_calculations')->insertGetId([
            'user_id' => $client->id,
            'daily_calories' => 2200,
            'protein_grams' => 165,
            'carbs_grams' => 220,
            'fats_grams' => 70,
            'goal' => 'weight_loss',
            'activity_multiplier' => 1.55,
            'created_by' => $coach->id,
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subMonths(3),
        ]);
        echo "   ✅ Nutrition plan created (2200 cal, 165P/220C/70F)\n\n";

        // 8. CREATE 3 MONTHS OF MEAL LOGS (3 meals/day = 270 logs)
        echo "🍽️ Creating 3 months of meal logs (270 meals)...\n";
        $mealCount = 0;

        for ($day = 0; $day < 90; $day++) {
            $date = now()->subDays(89 - $day);

            if ($date->isFuture()) continue;

            // Breakfast
            DB::table('meal_logs')->insert([
                'user_id' => $client->id,
                'meal_type' => 'breakfast',
                'calories' => rand(450, 550),
                'protein' => rand(30, 40),
                'carbs' => rand(50, 65),
                'fats' => rand(15, 22),
                'meal_name' => ['Oatmeal with berries and protein', 'Egg whites and avocado toast', 'Greek yogurt parfait'][rand(0, 2)],
                'logged_at' => $date->setTime(8, rand(0, 30)),
                'created_at' => $date->setTime(8, rand(0, 30)),
            ]);

            // Lunch
            DB::table('meal_logs')->insert([
                'user_id' => $client->id,
                'meal_type' => 'lunch',
                'calories' => rand(550, 700),
                'protein' => rand(45, 60),
                'carbs' => rand(60, 80),
                'fats' => rand(18, 28),
                'meal_name' => ['Grilled chicken salad', 'Turkey wrap with veggies', 'Salmon with quinoa'][rand(0, 2)],
                'logged_at' => $date->setTime(12, rand(30, 59)),
                'created_at' => $date->setTime(12, rand(30, 59)),
            ]);

            // Dinner
            DB::table('meal_logs')->insert([
                'user_id' => $client->id,
                'meal_type' => 'dinner',
                'calories' => rand(600, 750),
                'protein' => rand(50, 70),
                'carbs' => rand(55, 75),
                'fats' => rand(20, 30),
                'meal_name' => ['Lean steak with sweet potato', 'Grilled fish with vegetables', 'Chicken stir-fry'][rand(0, 2)],
                'logged_at' => $date->setTime(18, rand(30, 59)),
                'created_at' => $date->setTime(18, rand(30, 59)),
            ]);

            // Snacks (2x day)
            for ($s = 0; $s < 2; $s++) {
                DB::table('meal_logs')->insert([
                    'user_id' => $client->id,
                    'meal_type' => 'snack',
                    'calories' => rand(150, 250),
                    'protein' => rand(10, 20),
                    'carbs' => rand(15, 30),
                    'fats' => rand(5, 12),
                    'meal_name' => ['Protein shake', 'Apple with almond butter', 'Greek yogurt', 'Mixed nuts'][rand(0, 3)],
                    'logged_at' => $date->setTime($s == 0 ? 10 : 15, rand(0, 59)),
                    'created_at' => $date->setTime($s == 0 ? 10 : 15, rand(0, 59)),
                ]);
            }

            $mealCount += 5;
        }
        echo "   ✅ {$mealCount} meal logs created\n\n";

        // 9. CREATE CBT PROGRAM PROGRESS
        echo "🧠 Creating CBT program progress...\n";

        // Assuming 8-week program with 9 lessons per week = 72 total lessons
        $completedLessons = 0;
        for ($week = 1; $week <= 8; $week++) {
            $weekDate = now()->subWeeks(8 - $week);

            for ($lesson = 1; $lesson <= 9; $lesson++) {
                // Client is currently on Week 6
                if ($week > 6) continue;

                $lessonDate = $weekDate->copy()->addDays($lesson);
                if ($lessonDate->isFuture()) continue;

                DB::table('cbt_lesson_completions')->insert([
                    'user_id' => $client->id,
                    'week' => $week,
                    'lesson_number' => $lesson,
                    'lesson_title' => "Week {$week} - Lesson {$lesson}",
                    'completed_at' => $lessonDate,
                    'rating' => rand(4, 5),
                    'notes' => $lesson % 3 == 0 ? 'Very helpful insights!' : null,
                    'created_at' => $lessonDate,
                ]);

                $completedLessons++;
            }
        }
        echo "   ✅ {$completedLessons} CBT lessons completed (Week 6/8)\n\n";

        // 10. CREATE BODYPOINTS HISTORY
        echo "⭐ Creating BodyPoints transaction history...\n";
        $pointsEvents = [
            ['action' => 'workout_completed', 'points' => 50, 'count' => $completedCount],
            ['action' => 'meal_logged', 'points' => 5, 'count' => $mealCount],
            ['action' => 'cbt_lesson_completed', 'points' => 25, 'count' => $completedLessons],
            ['action' => 'streak_bonus', 'points' => 100, 'count' => 5],
            ['action' => 'achievement_unlocked', 'points' => 200, 'count' => 8],
        ];

        $totalPoints = 0;
        foreach ($pointsEvents as $event) {
            for ($i = 0; $i < $event['count']; $i++) {
                $date = now()->subDays(rand(1, 90));

                DB::table('body_points')->insert([
                    'user_id' => $client->id,
                    'points' => $event['points'],
                    'action' => $event['action'],
                    'description' => ucfirst(str_replace('_', ' ', $event['action'])),
                    'created_at' => $date,
                ]);

                $totalPoints += $event['points'];
            }
        }
        echo "   ✅ {$totalPoints} BodyPoints earned across " . array_sum(array_column($pointsEvents, 'count')) . " transactions\n\n";

        // 11. CREATE ACHIEVEMENTS
        echo "🏆 Creating achievement unlocks...\n";
        $achievements = [
            ['name' => 'First Workout', 'rarity' => 'common', 'earned_at' => now()->subMonths(3)],
            ['name' => '7-Day Streak', 'rarity' => 'rare', 'earned_at' => now()->subMonths(2)->subWeeks(3)],
            ['name' => '10 Workouts', 'rarity' => 'rare', 'earned_at' => now()->subMonths(2)->subWeeks(2)],
            ['name' => '5kg Weight Loss', 'rarity' => 'epic', 'earned_at' => now()->subMonths(1)->subWeeks(2)],
            ['name' => '25 Workouts', 'rarity' => 'rare', 'earned_at' => now()->subMonths(1)],
            ['name' => '21-Day Streak', 'rarity' => 'epic', 'earned_at' => now()->subWeeks(3)],
            ['name' => '10kg Weight Loss', 'rarity' => 'legendary', 'earned_at' => now()->subWeeks(1)],
            ['name' => 'Complete CBT Week 5', 'rarity' => 'epic', 'earned_at' => now()->subWeeks(2)],
        ];

        foreach ($achievements as $index => $achievement) {
            DB::table('achievements')->insert([
                'user_id' => $client->id,
                'name' => $achievement['name'],
                'rarity' => $achievement['rarity'],
                'icon' => 'trophy',
                'points_awarded' => ['common' => 50, 'rare' => 100, 'epic' => 200, 'legendary' => 500][$achievement['rarity']],
                'earned_at' => $achievement['earned_at'],
                'created_at' => $achievement['earned_at'],
            ]);
        }
        echo "   ✅ " . count($achievements) . " achievements unlocked\n\n";

        // 12. CREATE ACTIVITY LOGS (for coach dashboard feed)
        echo "📝 Creating activity logs for coach dashboard...\n";
        $activityCount = 0;

        // Recent workout activities
        for ($i = 0; $i < 10; $i++) {
            $date = now()->subDays($i * 2);
            DB::table('activity_logs')->insert([
                'user_id' => $client->id,
                'action_type' => 'workout_completed',
                'description' => 'Completed ' . $workouts[rand(0, 3)]['name'],
                'created_at' => $date,
            ]);
            $activityCount++;
        }

        // Recent meal logs
        for ($i = 0; $i < 10; $i++) {
            $date = now()->subHours(rand(1, 72));
            DB::table('activity_logs')->insert([
                'user_id' => $client->id,
                'action_type' => 'meal_logged',
                'description' => 'Logged ' . ['breakfast', 'lunch', 'dinner'][rand(0, 2)],
                'created_at' => $date,
            ]);
            $activityCount++;
        }

        echo "   ✅ {$activityCount} activity logs created\n\n";

        // SUMMARY
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "✨ SAMPLE DATA CREATION COMPLETE!\n";
        echo str_repeat("=", 70) . "\n\n";

        echo "📋 DEMO CREDENTIALS:\n";
        echo "   🏢 Organization: BodyF1rst Demo Corporation (ID: {$organization->id})\n";
        echo "   👨‍🏫 Coach: demo-coach@bodyf1rst.com / coach123\n";
        echo "   👤 Client: demo-client@bodyf1rst.com / client123\n\n";

        echo "📊 DATA SUMMARY:\n";
        echo "   • 12 weeks of body measurements (10kg weight loss progress)\n";
        echo "   • {$completedCount} completed workouts over 3 months\n";
        echo "   • {$mealCount} meal logs with full macro tracking\n";
        echo "   • {$completedLessons} CBT lessons completed (Week 6/8)\n";
        echo "   • {$totalPoints} BodyPoints earned\n";
        echo "   • " . count($achievements) . " achievements unlocked\n";
        echo "   • {$activityCount} recent activities for dashboard feed\n\n";

        echo "🎯 READY FOR TESTING:\n";
        echo "   1. Login as coach: admin.bodyf1rst.com\n";
        echo "   2. View client progress graphs and analytics\n";
        echo "   3. Create workout/meal plan\n";
        echo "   4. Assign to organization → verify client receives it\n\n";

        echo "🚀 All data is ready for your meeting demo!\n";
        echo str_repeat("=", 70) . "\n";
    }
}
