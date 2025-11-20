<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Coach;
use App\Models\Organization;
use App\Models\SpiritMindsetData;
use App\Models\AssessmentData;
use App\Models\DailyTask;
use App\Models\NutritionLog;

/**
 * WARNING: FOR DEMO AND PRESENTATION PURPOSES ONLY
 * DO NOT run this seeder in production environments
 * This creates comprehensive demo data with 3 months of historical information
 * including workouts, nutrition logs, CBT lessons, and assessment data
 */
class CompleteDemoUserSeeder extends Seeder
{
    /**
     * Seed a complete demo user with 3 months of comprehensive historical data
     * Including workouts, nutrition, CBT/Spirit data, 3D Avatar usage, and the 5-point assessment system
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('Skipping CompleteDemoUserSeeder in production environment');
            return;
        }
        echo "🚀 Creating comprehensive demo data...\n\n";

        // 1. CREATE ORGANIZATION
        echo "📊 Creating demo organization...\n";
        $organization = Organization::updateOrCreate(
            ['email' => 'demo-org@bodyf1rst.com'],
            [
                'name' => 'BodyF1rst Demo Corporation',
                'contact_person' => 'John Demo Manager',
                'phone' => '+1-555-DEMO-123',
                'address' => '123 Fitness Avenue, Demo City, CA 90210',
                'status' => 'active',
                'contract_start_date' => now()->subMonths(6)->toDateString(),
                'contract_end_date' => now()->addMonths(6)->toDateString(),
                'created_at' => now()->subMonths(6),
            ]
        );
        echo "   ✅ Organization: {$organization->name} (ID: {$organization->id})\n\n";

        // 2. CREATE COACH
        echo "👨‍🏫 Creating demo coach...\n";
        $coach = Coach::updateOrCreate(
            ['email' => 'demo-coach@bodyf1rst.com'],
            [
                'name' => 'Sarah Johnson',
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'phone' => '+1-555-COACH-01',
                'password' => Hash::make('coach123'),
                'is_active' => 1,
                'specialization' => 'Strength & Nutrition',
                'certification' => 'NASM-CPT, Precision Nutrition L1',
                'bio' => 'Certified personal trainer with 8+ years experience.',
                'created_at' => now()->subMonths(6),
            ]
        );
        echo "   ✅ Coach: {$coach->name} (demo-coach@bodyf1rst.com / coach123)\n\n";

        DB::table('coach_organizations')->updateOrInsert(
            ['coach_id' => $coach->id, 'organization_id' => $organization->id],
            ['created_at' => now()->subMonths(6), 'updated_at' => now()]
        );

        // 3. CREATE DEMO CLIENT
        echo "👤 Creating demo client with 3 months of comprehensive data...\n";
        $client = User::updateOrCreate(
            ['email' => 'demo-client@bodyf1rst.com'],
            [
                'first_name' => 'Michael',
                'last_name' => 'Thompson',
                'password' => Hash::make('client123'),
                'plain_password' => 'client123',
                'phone' => '+1-555-CLIENT-01',
                'is_active' => 1,
                'email_verified_at' => now()->subMonths(3),
                'gender' => 'male',
                'age' => 32,
                'dob' => now()->subYears(32)->toDateString(),
                'height' => 178,
                'weight' => 85,
                'goal' => 'weight_loss',
                'activity_level' => 'moderately_active',
                'dietary_restrictions' => json_encode(['gluten_free']),
                'body_points' => 2850, // Accumulated over 3 months
                'organization_id' => $organization->id,
                'coach_id' => $coach->id,
                'created_at' => now()->subMonths(3),
            ]
        );
        echo "   ✅ Client: {$client->first_name} {$client->last_name} (demo-client@bodyf1rst.com / client123)\n";
        echo "   📊 BodyPoints: 2850 | Goal: Weight Loss\n\n";

        DB::table('coach_users')->updateOrInsert(
            ['coach_id' => $coach->id, 'user_id' => $client->id],
            ['assigned_at' => now()->subMonths(3), 'created_at' => now()->subMonths(3)]
        );

        // 4. CREATE BODY MEASUREMENTS (Weekly for 12 weeks)
        echo "📈 Creating body measurement history (12 weeks)...\n";
        $this->createBodyMeasurements($client->id);
        echo "   ✅ 12 weekly measurements created\n\n";

        // 5. CREATE WORKOUT PLAN & HISTORY
        echo "💪 Creating workout plan and history...\n";
        $workoutData = $this->createWorkoutPlanAndHistory($client->id, $coach->id);
        echo "   ✅ {$workoutData['completed_count']} workouts completed over 3 months\n\n";

        // 6. CREATE NUTRITION PLAN & MEAL LOGS
        echo "🥗 Creating nutrition plan and meal logs...\n";
        $mealCount = $this->createNutritionHistory($client->id, $coach->id);
        echo "   ✅ {$mealCount} nutrition logs created\n\n";

        // 7. CREATE CBT LESSON COMPLETIONS
        echo "🧠 Creating CBT lesson completions...\n";
        $cbtCount = $this->createCBTLessons($client->id);
        echo "   ✅ {$cbtCount} CBT lessons completed\n\n";

        // 8. CREATE SPIRIT & MINDSET DATA (Daily with 3D Avatar usage)
        echo "✨ Creating Spirit & Mindset data with 3D Avatar interactions...\n";
        $spiritDays = $this->createSpiritMindsetData($client->id);
        echo "   ✅ {$spiritDays} days of Spirit/Mindset data with 3D Avatar interactions\n\n";

        // 9. CREATE DAILY TASKS
        echo "📋 Creating daily tasks...\n";
        $taskCount = $this->createDailyTasks($client->id);
        echo "   ✅ {$taskCount} daily tasks created\n\n";

        // 10. CREATE ASSESSMENT DATA (5-Point System)
        echo "📊 Creating 5-Point Assessment System upgrades...\n";
        $this->createAssessmentData($client->id);
        echo "   ✅ 5-Point assessment with upgrades created\n\n";

        // 11. CREATE BODYPOINTS HISTORY
        echo "⭐ Creating BodyPoints transaction history...\n";
        $pointsCount = $this->createBodyPointsHistory($client->id, $workoutData['completed_count'], $mealCount, $cbtCount);
        echo "   ✅ {$pointsCount} BodyPoints transactions created\n\n";

        // 12. CREATE ACHIEVEMENTS
        echo "🏆 Creating achievements...\n";
        $achievementCount = $this->createAchievements($client->id);
        echo "   ✅ {$achievementCount} achievements unlocked\n\n";

        // SUMMARY
        $this->printSummary($organization, $coach, $client, $workoutData['completed_count'], $mealCount, $cbtCount, $spiritDays);
    }

    private function createBodyMeasurements($userId)
    {
        $startWeight = 95;
        $endWeight = 85;

        for ($week = 0; $week < 12; $week++) {
            $progress = $week / 11;
            $date = now()->subWeeks(11 - $week);
            $weight = $startWeight - (($startWeight - $endWeight) * $progress);

            if (DB::table('body_measurements')->where('user_id', $userId)->where('measured_at', $date)->exists()) {
                continue;
            }

            DB::table('body_measurements')->insert([
                'user_id' => $userId,
                'weight' => round($weight, 1),
                'body_fat_percentage' => round(28 - ($progress * 6), 1),
                'muscle_mass' => round(56 + ($progress * 4), 1),
                'chest' => 98 - ($progress * 5),
                'waist' => 92 - ($progress * 8),
                'hips' => 102 - ($progress * 4),
                'measured_at' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    private function createWorkoutPlanAndHistory($userId, $coachId)
    {
        // Create workout plan
        $workoutPlan = DB::table('plans')->insertGetId([
            'name' => 'Fat Loss Transformation - 12 Week Program',
            'description' => 'Comprehensive strength training program',
            'duration' => 12,
            'difficulty' => 'intermediate',
            'goal' => 'weight_loss',
            'created_by' => $coachId,
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subMonths(3),
        ]);

        // Create workouts
        $workouts = [
            ['name' => 'Full Body Strength A', 'duration' => 45, 'category' => 'strength'],
            ['name' => 'Upper Body Push', 'duration' => 40, 'category' => 'strength'],
            ['name' => 'Lower Body & Core', 'duration' => 50, 'category' => 'strength'],
            ['name' => 'HIIT Cardio', 'duration' => 30, 'category' => 'cardio'],
        ];

        $workoutIds = [];
        foreach ($workouts as $workout) {
            $workoutIds[] = DB::table('workouts')->insertGetId(array_merge($workout, [
                'description' => 'Workout description',
                'difficulty' => 'intermediate',
                'created_by' => $coachId,
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(3),
            ]));
        }

        // Assign plan to user
        DB::table('assign_plans')->updateOrInsert(
            ['user_id' => $userId, 'plan_id' => $workoutPlan],
            [
                'assigned_by' => $coachId,
                'assigned_at' => now()->subMonths(3),
                'status' => 'active',
                'created_at' => now()->subMonths(3),
            ]
        );

        // Create completed workouts (4x/week for 12 weeks = ~48 workouts)
        $completedCount = 0;
        for ($week = 0; $week < 12; $week++) {
            $days = [1, 3, 5, 6]; // Mon, Wed, Fri, Sat
            foreach ($days as $dayIndex => $dayOfWeek) {
                $workoutDate = now()->subWeeks(11 - $week)->startOfWeek()->addDays($dayOfWeek - 1);
                if ($workoutDate->isFuture()) continue;

                DB::table('user_completed_workouts')->insert([
                    'user_id' => $userId,
                    'workout_id' => $workoutIds[$dayIndex],
                    'plan_id' => $workoutPlan,
                    'status' => 'completed',
                    'completed_at' => $workoutDate,
                    'created_at' => $workoutDate,
                    'updated_at' => $workoutDate,
                ]);
                $completedCount++;
            }
        }

        return ['plan_id' => $workoutPlan, 'completed_count' => $completedCount];
    }

    private function createNutritionHistory($userId, $coachId)
    {
        // Create nutrition calculation
        DB::table('nutrition_calculations')->updateOrInsert(
            ['user_id' => $userId],
            [
                'daily_calories' => 2200,
                'protein_grams' => 165,
                'carbs_grams' => 220,
                'fats_grams' => 70,
                'goal' => 'weight_loss',
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(3),
            ]
        );

        // Create meal logs for 90 days
        $mealCount = 0;
        for ($day = 0; $day < 90; $day++) {
            $date = now()->subDays(89 - $day)->toDateString();
            if (Carbon::parse($date)->isFuture()) continue;

            // Check if nutrition log already exists
            if (NutritionLog::where('user_id', $userId)->where('date', $date)->exists()) {
                continue;
            }

            $meals = [
                ['type' => 'breakfast', 'name' => 'Oatmeal with protein', 'cals' => 500, 'p' => 35, 'c' => 60, 'f' => 18],
                ['type' => 'lunch', 'name' => 'Grilled chicken salad', 'cals' => 650, 'p' => 55, 'c' => 70, 'f' => 24],
                ['type' => 'dinner', 'name' => 'Salmon with vegetables', 'cals' => 700, 'p' => 60, 'c' => 65, 'f' => 26],
                ['type' => 'snack', 'name' => 'Protein shake', 'cals' => 200, 'p' => 15, 'c' => 25, 'f' => 5],
            ];

            $totalCals = 0;
            $totalProtein = 0;
            $totalCarbs = 0;
            $totalFat = 0;
            $mealsData = [];

            foreach ($meals as $meal) {
                $totalCals += $meal['cals'];
                $totalProtein += $meal['p'];
                $totalCarbs += $meal['c'];
                $totalFat += $meal['f'];
                $mealsData[] = [
                    'type' => $meal['type'],
                    'name' => $meal['name'],
                    'calories' => $meal['cals'],
                    'protein' => $meal['p'],
                    'carbs' => $meal['c'],
                    'fats' => $meal['f'],
                ];
            }

            NutritionLog::create([
                'user_id' => $userId,
                'date' => $date,
                'meals' => $mealsData,
                'total_calories' => $totalCals,
                'macros' => [
                    'protein' => $totalProtein,
                    'carbs' => $totalCarbs,
                    'fats' => $totalFat,
                ],
                'water_intake' => rand(2000, 3500) / 1000, // 2-3.5 liters
                'created_at' => Carbon::parse($date),
            ]);
            $mealCount++;
        }

        return $mealCount;
    }

    private function createCBTLessons($userId)
    {
        $completedLessons = 0;
        for ($week = 1; $week <= 8; $week++) {
            for ($lesson = 1; $lesson <= 9; $lesson++) {
                if ($week > 6) continue; // Currently on week 6

                $lessonDate = now()->subWeeks(8 - $week)->addDays($lesson);
                if ($lessonDate->isFuture()) continue;

                DB::table('cbt_lesson_completions')->insert([
                    'user_id' => $userId,
                    'week' => $week,
                    'lesson_number' => $lesson,
                    'lesson_title' => "Week {$week} - Lesson {$lesson}",
                    'completed_at' => $lessonDate,
                    'rating' => rand(4, 5),
                    'created_at' => $lessonDate,
                ]);
                $completedLessons++;
            }
        }
        return $completedLessons;
    }

    private function createSpiritMindsetData($userId)
    {
        $daysCreated = 0;
        $avatarInteractionTypes = [
            'meditation_session',
            'breathing_exercise',
            'guided_visualization',
            'positive_affirmations',
            'mindfulness_practice',
        ];

        for ($day = 0; $day < 90; $day++) {
            $date = now()->subDays(89 - $day)->toDateString();
            if (Carbon::parse($date)->isFuture()) continue;

            // 70% of days have data
            if (rand(1, 10) > 7) continue;

            if (SpiritMindsetData::where('user_id', $userId)->where('date', $date)->exists()) {
                continue;
            }

            SpiritMindsetData::create([
                'user_id' => $userId,
                'date' => $date,
                'data' => [
                    'morningMindset' => [
                        'enthusiasm' => 'I am energized and ready for today',
                        'beWord' => ['Confident', 'Strong', 'Focused', 'Grateful'][rand(0, 3)],
                        'action' => 'Complete workout and meal prep',
                        'success' => 'Staying consistent with my plan',
                        'service' => 'Help a friend with their fitness goals',
                    ],
                    'morningGratitude' => [
                        'My health and fitness journey',
                        'Support from my coach',
                        'Progress I\'ve made',
                    ],
                    'eveningGratitude' => [
                        'Completed today\'s workout',
                        'Made healthy food choices',
                        'Learned something new',
                    ],
                    'topOutcomes' => [
                        'Lost 10kg in 3 months',
                        'Increased strength and energy',
                        'Developed healthy habits',
                    ],
                    'successList' => [
                        'spiritual' => rand(0, 1) == 1,
                        'mindset' => rand(0, 1) == 1,
                        'mealPlan' => rand(0, 1) == 1,
                        'workout' => rand(0, 1) == 1,
                    ],
                    // 3D Avatar interaction tracking
                    'avatar_interactions' => [
                        'session_type' => $avatarInteractionTypes[rand(0, 4)],
                        'duration_minutes' => rand(5, 20),
                        'completed' => true,
                        'rating' => rand(4, 5),
                        'notes' => 'Great session with 3D avatar guide',
                    ],
                    'avatar_usage_count' => rand(1, 3), // 1-3 interactions per day
                ],
                'created_at' => Carbon::parse($date),
            ]);
            $daysCreated++;
        }

        return $daysCreated;
    }

    private function createDailyTasks($userId)
    {
        $taskCount = 0;
        $goalTemplates = [
            'Complete morning meditation',
            'Drink 2L of water',
            'Hit protein target',
            'Complete workout',
            'Practice gratitude journaling',
            'Do 10 minutes of stretching',
            'Meal prep for tomorrow',
        ];

        for ($day = 0; $day < 90; $day++) {
            $date = now()->subDays(89 - $day)->toDateString();
            if (Carbon::parse($date)->isFuture()) continue;

            // Create 3-5 tasks per day
            $numTasks = rand(3, 5);
            for ($t = 0; $t < $numTasks; $t++) {
                DailyTask::create([
                    'user_id' => $userId,
                    'date' => $date,
                    'goal' => $goalTemplates[rand(0, count($goalTemplates) - 1)],
                    'time' => rand(6, 20) . ':' . (rand(0, 1) ? '00' : '30'),
                    'scheduled' => true,
                    'completed' => rand(0, 10) > 3, // 70% completion rate
                    'created_at' => Carbon::parse($date),
                ]);
                $taskCount++;
            }
        }

        return $taskCount;
    }

    private function createAssessmentData($userId)
    {
        // 5-Point Assessment System with progressive upgrades
        $initialScores = [
            'physical_fitness' => 45,
            'nutrition_habits' => 40,
            'mental_wellbeing' => 50,
            'sleep_quality' => 35,
            'stress_management' => 38,
        ];

        $currentScores = [
            'physical_fitness' => 78, // +33 points (upgraded)
            'nutrition_habits' => 72, // +32 points (upgraded)
            'mental_wellbeing' => 80, // +30 points (upgraded)
            'sleep_quality' => 68, // +33 points (upgraded)
            'stress_management' => 75, // +37 points (upgraded)
        ];

        AssessmentData::updateOrCreate(
            ['user_id' => $userId],
            [
                'scores' => [
                    'initial_assessment' => $initialScores,
                    'current_scores' => $currentScores,
                    'assessment_dates' => [
                        'initial' => now()->subMonths(3)->toDateString(),
                        'last_update' => now()->toDateString(),
                    ],
                    'improvements' => [
                        'physical_fitness' => '+33 pts',
                        'nutrition_habits' => '+32 pts',
                        'mental_wellbeing' => '+30 pts',
                        'sleep_quality' => '+33 pts',
                        'stress_management' => '+37 pts',
                    ],
                    'overall_grade' => 'B+',
                    'progress_percentage' => 68,
                ],
                'created_at' => now()->subMonths(3),
                'updated_at' => now(),
            ]
        );
    }

    private function createBodyPointsHistory($userId, $workoutCount, $mealCount, $cbtCount)
    {
        $transactions = 0;

        // Workout points (50 points each)
        for ($i = 0; $i < $workoutCount; $i++) {
            DB::table('transactions')->insert([
                'user_id' => $userId,
                'points' => 50,
                'type' => 'earned',
                'description' => 'Workout completed',
                'created_at' => now()->subDays(rand(1, 90)),
            ]);
            $transactions++;
        }

        // CBT lesson points (25 points each)
        for ($i = 0; $i < $cbtCount; $i++) {
            DB::table('transactions')->insert([
                'user_id' => $userId,
                'points' => 25,
                'type' => 'earned',
                'description' => 'CBT lesson completed',
                'created_at' => now()->subDays(rand(1, 90)),
            ]);
            $transactions++;
        }

        // Avatar interaction points (10 points each)
        for ($i = 0; $i < 60; $i++) {
            DB::table('transactions')->insert([
                'user_id' => $userId,
                'points' => 10,
                'type' => 'earned',
                'description' => '3D Avatar session completed',
                'created_at' => now()->subDays(rand(1, 90)),
            ]);
            $transactions++;
        }

        // Bonus points
        $bonuses = [
            ['points' => 100, 'desc' => '7-day streak bonus', 'count' => 8],
            ['points' => 200, 'desc' => 'Assessment upgrade bonus', 'count' => 5],
            ['points' => 150, 'desc' => 'Monthly challenge completed', 'count' => 3],
        ];

        foreach ($bonuses as $bonus) {
            for ($i = 0; $i < $bonus['count']; $i++) {
                DB::table('transactions')->insert([
                    'user_id' => $userId,
                    'points' => $bonus['points'],
                    'type' => 'earned',
                    'description' => $bonus['desc'],
                    'created_at' => now()->subDays(rand(1, 90)),
                ]);
                $transactions++;
            }
        }

        return $transactions;
    }

    private function createAchievements($userId)
    {
        $achievements = [
            ['name' => 'First Workout', 'rarity' => 'common', 'points' => 50, 'date' => now()->subMonths(3)],
            ['name' => '7-Day Streak', 'rarity' => 'rare', 'points' => 100, 'date' => now()->subMonths(2)->subWeeks(3)],
            ['name' => '5kg Weight Loss', 'rarity' => 'epic', 'points' => 200, 'date' => now()->subMonths(1)->subWeeks(2)],
            ['name' => '25 Workouts', 'rarity' => 'rare', 'points' => 100, 'date' => now()->subMonths(1)],
            ['name' => '10kg Weight Loss', 'rarity' => 'legendary', 'points' => 500, 'date' => now()->subWeeks(1)],
            ['name' => 'CBT Week 5 Complete', 'rarity' => 'epic', 'points' => 200, 'date' => now()->subWeeks(2)],
            ['name' => '30-Day Avatar Practice', 'rarity' => 'epic', 'points' => 200, 'date' => now()->subWeeks(3)],
            ['name' => '5-Point Assessment Upgrade', 'rarity' => 'legendary', 'points' => 500, 'date' => now()->subDays(5)],
        ];

        foreach ($achievements as $achievement) {
            DB::table('achievements')->insert([
                'user_id' => $userId,
                'name' => $achievement['name'],
                'rarity' => $achievement['rarity'],
                'icon' => 'trophy',
                'points_awarded' => $achievement['points'],
                'earned_at' => $achievement['date'],
                'created_at' => $achievement['date'],
            ]);
        }

        return count($achievements);
    }

    private function printSummary($organization, $coach, $client, $workoutCount, $mealCount, $cbtCount, $spiritDays)
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "✨ COMPREHENSIVE DEMO DATA CREATED SUCCESSFULLY!\n";
        echo str_repeat("=", 80) . "\n\n";

        echo "📋 CREDENTIALS:\n";
        echo "   🏢 Organization: {$organization->name}\n";
        echo "   👨‍🏫 Coach: demo-coach@bodyf1rst.com / coach123\n";
        echo "   👤 Client: demo-client@bodyf1rst.com / client123\n\n";

        echo "📊 DATA SUMMARY (3 months):\n";
        echo "   💪 Workouts: {$workoutCount} completed sessions\n";
        echo "   🥗 Nutrition: {$mealCount} days of meal tracking\n";
        echo "   🧠 CBT: {$cbtCount} lessons completed (Week 6/8)\n";
        echo "   ✨ Spirit/Mindset: {$spiritDays} days with 3D Avatar interactions\n";
        echo "   📊 5-Point Assessment: All categories upgraded 30+ points\n";
        echo "   ⭐ BodyPoints: 2850 points accumulated\n";
        echo "   🏆 Achievements: 8 unlocked\n";
        echo "   📈 Progress: 10kg weight loss (95kg → 85kg)\n\n";

        echo "🎯 DEMO FEATURES:\n";
        echo "   ✅ 3 months of workout history\n";
        echo "   ✅ Daily nutrition tracking\n";
        echo "   ✅ CBT program progress\n";
        echo "   ✅ 3D Avatar interaction logs in Spirit/Mindset data\n";
        echo "   ✅ 5-Point assessment system with upgrades\n";
        echo "   ✅ BodyPoints gamification system\n";
        echo "   ✅ Achievement tracking\n\n";

        echo "🚀 Ready for demo presentation!\n";
        echo str_repeat("=", 80) . "\n";
    }
}
