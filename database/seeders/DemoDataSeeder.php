<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the demo data seeder.
     * Creates REAL coach Ken Laney, 2 demo users with 3 months of realistic data,
     * and BodyF1rst organization with all features demonstrated.
     */
    public function run()
    {
        echo "🎬 Creating BodyF1rst Demo Data...\n\n";

        // 1. Update existing admin passwords to Password123!
        $this->updateAdminPasswords();

        // 2. Create BodyF1rst organization
        $organizationId = $this->createOrganization();

        // 3. Create REAL Coach Ken Laney
        $coachId = $this->createCoachKen($organizationId);

        // 4. Create Male Demo User
        $maleUserId = $this->createMaleDemoUser($coachId);

        // 5. Create Female Demo User
        $femaleUserId = $this->createFemaleDemoUser($coachId);

        // 6. Generate 3 months of workout data
        $this->generateWorkoutData($maleUserId, $coachId);
        $this->generateWorkoutData($femaleUserId, $coachId);

        // 7. Generate 3 months of nutrition data
        $this->generateNutritionData($maleUserId);
        $this->generateNutritionData($femaleUserId);

        // 8. Generate CBT journal entries
        $this->generateCBTData($maleUserId);
        $this->generateCBTData($femaleUserId);

        // 9. Generate messages and group chat
        $this->generateMessages($coachId, $maleUserId, $femaleUserId);

        // 10. Generate challenges and achievements
        $this->generateChallengesAndAchievements($maleUserId, $femaleUserId);

        // 11. Generate body measurements and progress
        $this->generateProgressData($maleUserId, true);
        $this->generateProgressData($femaleUserId, false);

        echo "\n✅ Demo data created successfully!\n";
        echo "==============================================\n";
        echo "👨‍🏫 REAL COACH: ken@bodyf1rst.com | Password123!\n";
        echo "👨 Male Demo: Male-demo@bodyf1rst.com | Password123!\n";
        echo "👩 Female Demo: Female-demo@bodyf1rst.com | Password123!\n";
        echo "🔑 Admins: charley@bodyf1rst.com | Password123!\n";
        echo "🔑 Admins: Dustin@bodyf1rst.com | Password123!\n";
        echo "==============================================\n";
    }

    private function updateAdminPasswords()
    {
        echo "🔐 Updating admin passwords to Password123!...\n";

        DB::table('admins')->whereIn('email', ['charley@bodyf1rst.com', 'Dustin@bodyf1rst.com'])
            ->update([
                'password' => Hash::make('Password123!'),
                'updated_at' => now()
            ]);

        echo "  ✅ Admin passwords updated\n\n";
    }

    private function createOrganization()
    {
        echo "🏢 Creating BodyF1rst organization...\n";

        // Check if organization already exists
        $existing = DB::table('organizations')->where('name', 'BodyF1rst')->first();

        if ($existing) {
            echo "  ℹ️  Organization already exists (ID: {$existing->id})\n\n";
            return $existing->id;
        }

        $orgId = DB::table('organizations')->insertGetId([
            'name' => 'BodyF1rst',
            'description' => 'Premier fitness and wellness coaching organization dedicated to transforming lives through personalized training, nutrition guidance, and mental wellness support.',
            'email' => 'info@bodyf1rst.com',
            'phone' => '+1-555-BODY-F1RST',
            'address' => '123 Wellness Avenue, Fitness City, FC 12345',
            'website' => 'https://bodyf1rst.com',
            'logo_url' => 'https://bodyf1rst-workout-video-storage.s3.us-east-1.amazonaws.com/avatars/bodyf1rst-logo.png',
            'is_active' => true,
            'created_at' => Carbon::now()->subYear(),
            'updated_at' => now(),
        ]);

        echo "  ✅ Organization created (ID: $orgId)\n\n";
        return $orgId;
    }

    private function createCoachKen($organizationId)
    {
        echo "👨‍🏫 Creating REAL Coach Ken Laney...\n";

        // Check if Ken already exists
        $existing = DB::table('admins')->where('email', 'ken@bodyf1rst.com')->first();

        if ($existing) {
            echo "  ℹ️  Coach Ken already exists (ID: {$existing->id})\n";
            echo "  🔄 Updating password to Password123!\n";
            DB::table('admins')->where('id', $existing->id)->update([
                'password' => Hash::make('Password123!'),
                'updated_at' => now()
            ]);
            echo "  ✅ Password updated\n\n";
            return $existing->id;
        }

        $coachId = DB::table('admins')->insertGetId([
            'first_name' => 'Ken',
            'last_name' => 'Laney',
            'email' => 'ken@bodyf1rst.com',
            'password' => Hash::make('Password123!'),
            'phone' => '+1-555-0100',
            'avatar' => 'https://bodyf1rst-workout-video-storage.s3.us-east-1.amazonaws.com/avatars/coach-ken-laney.jpg',
            'bio' => 'Certified personal trainer and nutrition coach with over 10 years of experience. Specializing in strength training, body transformation, and sustainable lifestyle changes. Passionate about helping clients achieve their fitness goals through evidence-based training and holistic wellness.',
            'specialization' => 'Strength & Conditioning, Nutrition Coaching, Body Transformation, Mental Wellness',
            'certifications' => 'NASM-CPT, Precision Nutrition L1, USAW Sports Performance Coach',
            'is_active' => true,
            'organization_id' => $organizationId,
            'created_at' => Carbon::now()->subMonths(8),
            'updated_at' => now(),
        ]);

        echo "  ✅ REAL Coach Ken Laney created (ID: $coachId)\n\n";
        return $coachId;
    }

    private function createMaleDemoUser($coachId)
    {
        echo "👨 Creating Male Demo User (John Demo)...\n";

        // Check if user exists
        $existing = DB::table('users')->where('email', 'Male-demo@bodyf1rst.com')->first();

        if ($existing) {
            echo "  ⚠️  Male demo user already exists (ID: {$existing->id})\n";
            echo "  🗑️  Deleting to recreate with fresh data...\n";

            // Delete related data
            DB::table('workouts')->where('user_id', $existing->id)->delete();
            DB::table('meals')->where('user_id', $existing->id)->delete();
            DB::table('cbt_journal_entries')->where('user_id', $existing->id)->delete();
            DB::table('body_measurements')->where('user_id', $existing->id)->delete();
            DB::table('gamification_stats')->where('user_id', $existing->id)->delete();
            DB::table('messages')->where('from_user_id', $existing->id)->orWhere('to_user_id', $existing->id)->delete();
            DB::table('user_achievements')->where('user_id', $existing->id)->delete();
            DB::table('challenge_participants')->where('user_id', $existing->id)->delete();
            DB::table('users')->where('id', $existing->id)->delete();
        }

        $userId = DB::table('users')->insertGetId([
            'first_name' => 'John',
            'last_name' => 'Demo',
            'email' => 'Male-demo@bodyf1rst.com',
            'password' => Hash::make('Password123!'),
            'phone' => '+1-555-DEMO-01',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'avatar' => 'https://bodyf1rst-workout-video-storage.s3.us-east-1.amazonaws.com/avatars/male-demo-avatar.jpg',
            'height' => 180, // cm (5'11")
            'current_weight' => 85, // kg (187 lbs)
            'goal_weight' => 78, // kg (172 lbs)
            'fitness_goal' => 'Build lean muscle, lose body fat, and improve overall strength and conditioning',
            'activity_level' => 'moderately_active',
            'dietary_preferences' => 'Balanced macro approach, moderate carbs',
            'is_active' => true,
            'coach_id' => $coachId,
            'body_points' => 2850,
            'streak_days' => 45,
            'email_verified_at' => Carbon::now()->subMonths(3),
            'created_at' => Carbon::now()->subMonths(3),
            'updated_at' => now(),
        ]);

        echo "  ✅ Male demo user created (ID: $userId)\n\n";
        return $userId;
    }

    private function createFemaleDemoUser($coachId)
    {
        echo "👩 Creating Female Demo User (Sarah Demo)...\n";

        // Check if user exists
        $existing = DB::table('users')->where('email', 'Female-demo@bodyf1rst.com')->first();

        if ($existing) {
            echo "  ⚠️  Female demo user already exists (ID: {$existing->id})\n";
            echo "  🗑️  Deleting to recreate with fresh data...\n";

            // Delete related data
            DB::table('workouts')->where('user_id', $existing->id)->delete();
            DB::table('meals')->where('user_id', $existing->id)->delete();
            DB::table('cbt_journal_entries')->where('user_id', $existing->id)->delete();
            DB::table('body_measurements')->where('user_id', $existing->id)->delete();
            DB::table('gamification_stats')->where('user_id', $existing->id)->delete();
            DB::table('messages')->where('from_user_id', $existing->id)->orWhere('to_user_id', $existing->id)->delete();
            DB::table('user_achievements')->where('user_id', $existing->id)->delete();
            DB::table('challenge_participants')->where('user_id', $existing->id)->delete();
            DB::table('users')->where('id', $existing->id)->delete();
        }

        $userId = DB::table('users')->insertGetId([
            'first_name' => 'Sarah',
            'last_name' => 'Demo',
            'email' => 'Female-demo@bodyf1rst.com',
            'password' => Hash::make('Password123!'),
            'phone' => '+1-555-DEMO-02',
            'date_of_birth' => '1992-08-22',
            'gender' => 'female',
            'avatar' => 'https://bodyf1rst-workout-video-storage.s3.us-east-1.amazonaws.com/avatars/female-demo-avatar.jpg',
            'height' => 165, // cm (5'5")
            'current_weight' => 68, // kg (150 lbs)
            'goal_weight' => 62, // kg (137 lbs)
            'fitness_goal' => 'Tone up, improve cardiovascular endurance, and develop sustainable healthy habits',
            'activity_level' => 'active',
            'dietary_preferences' => 'Whole foods focus, plant-forward with lean proteins',
            'is_active' => true,
            'coach_id' => $coachId,
            'body_points' => 3200,
            'streak_days' => 52,
            'email_verified_at' => Carbon::now()->subMonths(3),
            'created_at' => Carbon::now()->subMonths(3),
            'updated_at' => now(),
        ]);

        echo "  ✅ Female demo user created (ID: $userId)\n\n";
        return $userId;
    }

    private function generateWorkoutData($userId, $coachId)
    {
        echo "💪 Generating 3 months of workout data for user $userId...\n";

        $workoutTemplates = [
            [
                'name' => 'Full Body Strength',
                'type' => 'strength',
                'description' => 'Complete full body workout focusing on compound movements',
                'exercises' => [
                    ['name' => 'Barbell Squat', 'sets' => 4, 'reps' => 8, 'rest' => 90, 'weight_range' => [60, 80]],
                    ['name' => 'Bench Press', 'sets' => 4, 'reps' => 10, 'rest' => 90, 'weight_range' => [40, 60]],
                    ['name' => 'Deadlift', 'sets' => 3, 'reps' => 6, 'rest' => 120, 'weight_range' => [80, 100]],
                    ['name' => 'Pull-ups', 'sets' => 3, 'reps' => 12, 'rest' => 60, 'weight_range' => [0, 0]],
                    ['name' => 'Overhead Press', 'sets' => 3, 'reps' => 10, 'rest' => 75, 'weight_range' => [30, 45]],
                ]
            ],
            [
                'name' => 'Upper Body Power',
                'type' => 'strength',
                'description' => 'Focus on upper body strength and muscle development',
                'exercises' => [
                    ['name' => 'Incline Dumbbell Press', 'sets' => 4, 'reps' => 10, 'rest' => 90, 'weight_range' => [25, 35]],
                    ['name' => 'Barbell Row', 'sets' => 4, 'reps' => 10, 'rest' => 75, 'weight_range' => [50, 70]],
                    ['name' => 'Dumbbell Shoulder Press', 'sets' => 3, 'reps' => 12, 'rest' => 60, 'weight_range' => [20, 30]],
                    ['name' => 'Cable Tricep Pushdown', 'sets' => 3, 'reps' => 15, 'rest' => 45, 'weight_range' => [30, 40]],
                    ['name' => 'Dumbbell Bicep Curls', 'sets' => 3, 'reps' => 12, 'rest' => 45, 'weight_range' => [15, 20]],
                ]
            ],
            [
                'name' => 'Lower Body Blast',
                'type' => 'strength',
                'description' => 'Leg day - building powerful lower body',
                'exercises' => [
                    ['name' => 'Front Squat', 'sets' => 4, 'reps' => 8, 'rest' => 90, 'weight_range' => [50, 70]],
                    ['name' => 'Romanian Deadlift', 'sets' => 4, 'reps' => 10, 'rest' => 90, 'weight_range' => [60, 80]],
                    ['name' => 'Bulgarian Split Squat', 'sets' => 3, 'reps' => 12, 'rest' => 60, 'weight_range' => [20, 30]],
                    ['name' => 'Leg Press', 'sets' => 3, 'reps' => 15, 'rest' => 75, 'weight_range' => [100, 140]],
                    ['name' => 'Calf Raises', 'sets' => 4, 'reps' => 20, 'rest' => 45, 'weight_range' => [40, 60]],
                ]
            ],
            [
                'name' => 'HIIT Cardio Blast',
                'type' => 'cardio',
                'description' => 'High intensity interval training for fat burning',
                'exercises' => [
                    ['name' => 'Burpees', 'sets' => 5, 'reps' => 15, 'rest' => 45, 'weight_range' => [0, 0]],
                    ['name' => 'Mountain Climbers', 'sets' => 5, 'reps' => 30, 'rest' => 45, 'weight_range' => [0, 0]],
                    ['name' => 'Jump Squats', 'sets' => 4, 'reps' => 20, 'rest' => 45, 'weight_range' => [0, 0]],
                    ['name' => 'High Knees', 'sets' => 4, 'reps' => 40, 'rest' => 45, 'weight_range' => [0, 0]],
                    ['name' => 'Box Jumps', 'sets' => 3, 'reps' => 15, 'rest' => 60, 'weight_range' => [0, 0]],
                ]
            ],
        ];

        // Generate 3 months of workouts (3-4x per week = ~40 workouts)
        $workoutCount = 0;
        for ($i = 0; $i < 90; $i++) {
            // Workout 3-4 times per week
            if ($i % 2 == 0 || $i % 3 == 0) {
                $date = Carbon::now()->subDays(90 - $i);
                $template = $workoutTemplates[$workoutCount % count($workoutTemplates)];

                $workoutId = DB::table('workouts')->insertGetId([
                    'user_id' => $userId,
                    'coach_id' => $coachId,
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'type' => $template['type'],
                    'scheduled_date' => $date,
                    'duration_minutes' => rand(40, 60),
                    'calories_burned' => rand(300, 550),
                    'status' => 'completed',
                    'notes' => $this->getRandomWorkoutNote(),
                    'completed_at' => $date->copy()->addHours(1),
                    'created_at' => $date->subHours(2),
                    'updated_at' => $date->copy()->addHours(1),
                ]);

                foreach ($template['exercises'] as $exercise) {
                    DB::table('workout_exercises')->insert([
                        'workout_id' => $workoutId,
                        'exercise_name' => $exercise['name'],
                        'sets' => $exercise['sets'],
                        'reps' => $exercise['reps'],
                        'weight' => rand($exercise['weight_range'][0], $exercise['weight_range'][1]),
                        'rest_seconds' => $exercise['rest'],
                        'completed' => true,
                        'notes' => $this->getRandomExerciseNote(),
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);
                }

                $workoutCount++;
            }
        }

        echo "  ✅ Created $workoutCount workouts with exercises\n";
    }

    private function generateNutritionData($userId)
    {
        echo "🍎 Generating 3 months of nutrition data for user $userId...\n";

        $meals = [
            'breakfast' => [
                ['name' => 'Oatmeal with berries and honey', 'calories' => 350, 'protein' => 12, 'carbs' => 58, 'fat' => 8],
                ['name' => 'Scrambled eggs with whole grain toast', 'calories' => 420, 'protein' => 24, 'carbs' => 35, 'fat' => 18],
                ['name' => 'Greek yogurt parfait with granola', 'calories' => 380, 'protein' => 20, 'carbs' => 45, 'fat' => 12],
                ['name' => 'Protein pancakes with fruit', 'calories' => 410, 'protein' => 28, 'carbs' => 48, 'fat' => 14],
                ['name' => 'Avocado toast with eggs', 'calories' => 440, 'protein' => 22, 'carbs' => 38, 'fat' => 22],
            ],
            'lunch' => [
                ['name' => 'Grilled chicken Caesar salad', 'calories' => 450, 'protein' => 38, 'carbs' => 25, 'fat' => 22],
                ['name' => 'Turkey and avocado wrap', 'calories' => 520, 'protein' => 32, 'carbs' => 48, 'fat' => 20],
                ['name' => 'Quinoa bowl with grilled salmon', 'calories' => 580, 'protein' => 36, 'carbs' => 52, 'fat' => 24],
                ['name' => 'Chicken burrito bowl', 'calories' => 550, 'protein' => 40, 'carbs' => 55, 'fat' => 18],
                ['name' => 'Tuna salad with mixed greens', 'calories' => 420, 'protein' => 35, 'carbs' => 22, 'fat' => 20],
            ],
            'dinner' => [
                ['name' => 'Grilled steak with sweet potato and broccoli', 'calories' => 620, 'protein' => 45, 'carbs' => 48, 'fat' => 26],
                ['name' => 'Baked chicken breast with brown rice and vegetables', 'calories' => 550, 'protein' => 42, 'carbs' => 55, 'fat' => 15],
                ['name' => 'Grilled fish tacos with avocado', 'calories' => 480, 'protein' => 32, 'carbs' => 42, 'fat' => 20],
                ['name' => 'Lean beef stir-fry with vegetables', 'calories' => 510, 'protein' => 38, 'carbs' => 45, 'fat' => 18],
                ['name' => 'Baked salmon with asparagus and quinoa', 'calories' => 580, 'protein' => 40, 'carbs' => 48, 'fat' => 24],
            ],
            'snack' => [
                ['name' => 'Protein shake with banana', 'calories' => 220, 'protein' => 25, 'carbs' => 22, 'fat' => 4],
                ['name' => 'Apple slices with almond butter', 'calories' => 240, 'protein' => 6, 'carbs' => 28, 'fat' => 14],
                ['name' => 'Mixed nuts and dried fruit', 'calories' => 200, 'protein' => 7, 'carbs' => 22, 'fat' => 11],
                ['name' => 'Protein bar', 'calories' => 210, 'protein' => 20, 'carbs' => 24, 'fat' => 7],
                ['name' => 'Cottage cheese with pineapple', 'calories' => 180, 'protein' => 18, 'carbs' => 20, 'fat' => 4],
            ],
        ];

        $mealCount = 0;
        // Generate 90 days of meals (4 meals per day)
        for ($day = 0; $day < 90; $day++) {
            $date = Carbon::now()->subDays(90 - $day);

            foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $mealType) {
                $meal = $meals[$mealType][array_rand($meals[$mealType])];
                $mealTime = [
                    'breakfast' => 8,
                    'lunch' => 13,
                    'dinner' => 19,
                    'snack' => 15
                ][$mealType];

                DB::table('meals')->insert([
                    'user_id' => $userId,
                    'meal_type' => $mealType,
                    'food_name' => $meal['name'],
                    'calories' => $meal['calories'],
                    'protein' => $meal['protein'],
                    'carbs' => $meal['carbs'],
                    'fat' => $meal['fat'],
                    'serving_size' => '1 serving',
                    'logged_at' => $date->copy()->setTime($mealTime, rand(0, 59)),
                    'created_at' => $date->copy()->setTime($mealTime, rand(0, 59)),
                    'updated_at' => $date->copy()->setTime($mealTime, rand(0, 59)),
                ]);
                $mealCount++;
            }
        }

        echo "  ✅ Created $mealCount meal logs (90 days × 4 meals)\n";
    }

    private function generateCBTData($userId)
    {
        echo "🧠 Generating CBT journal entries for user $userId...\n";

        $entries = [
            [
                'title' => 'Feeling stressed about work deadlines',
                'situation' => 'Had a major project deadline approaching at work and felt completely overwhelmed by the amount of work remaining.',
                'thoughts' => 'I\'ll never finish this on time. I\'m going to fail. Everyone will think I\'m incompetent.',
                'emotions' => 'Anxious (8/10), Stressed (9/10), Inadequate (7/10)',
                'behaviors' => 'Procrastinated by scrolling social media, avoided looking at task list, stress-ate junk food, stayed up late worrying',
                'alternative_thoughts' => 'I can break this down into smaller, manageable tasks. I\'ve successfully met deadlines before. Asking for help is a sign of strength, not weakness.',
                'outcome' => 'After breaking the project into daily goals and requesting help from colleagues, I completed it on time. Felt proud and realized my anxiety was worse than reality.',
                'mood_before' => 3,
                'mood_after' => 8,
            ],
            [
                'title' => 'Proud of hitting new personal record',
                'situation' => 'Hit a new PR on deadlifts - lifted 20 lbs more than my previous max. Coach Ken was there to spot and celebrate with me.',
                'thoughts' => 'All my hard work and consistency is finally paying off! I\'m getting genuinely stronger and it feels amazing.',
                'emotions' => 'Proud (9/10), Accomplished (9/10), Motivated (10/10), Excited (8/10)',
                'behaviors' => 'Celebrated with a healthy post-workout meal, texted Coach Ken a thank you, posted progress pic on social media, called family to share',
                'alternative_thoughts' => 'This success came from months of showing up consistently, even when I didn't feel like it. Discipline beats motivation.',
                'outcome' => 'Felt incredibly energized and motivated to continue pushing myself. This victory reminded me why I started this journey.',
                'mood_before' => 7,
                'mood_after' => 10,
            ],
            [
                'title' => 'Dealing with social anxiety at party',
                'situation' => 'Friend invited me to a birthday party with lots of people I didn\'t know. Felt anxious about attending and almost cancelled last minute.',
                'thoughts' => 'Nobody will want to talk to me. I\'ll stand awkwardly in the corner. People will think I\'m boring or weird.',
                'emotions' => 'Anxious (7/10), Nervous (8/10), Self-conscious (7/10), Fearful (6/10)',
                'behaviors' => 'Almost texted to cancel, practiced deep breathing, decided to commit to staying for just one hour',
                'alternative_thoughts' => 'My close friend invited me because they value me. Most people feel nervous at parties. It\'s okay to feel anxious and still go. I can leave anytime.',
                'outcome' => 'Had a better time than expected! Met 3 interesting people and my anxiety decreased significantly after 20 minutes. Glad I pushed through.',
                'mood_before' => 4,
                'mood_after' => 7,
            ],
            [
                'title' => 'Frustrated with weight loss plateau',
                'situation' => 'Stepped on the scale and haven\'t lost any weight in 2 weeks despite being consistent with workouts and nutrition.',
                'thoughts' => 'Nothing I do is working. I\'ll never reach my goal weight. Maybe I\'m just destined to stay this way.',
                'emotions' => 'Frustrated (8/10), Disappointed (7/10), Discouraged (7/10)',
                'behaviors' => 'Considered quitting, skipped evening snack out of frustration, almost didn\'t go to scheduled workout',
                'alternative_thoughts' => 'Plateaus are normal and don\'t mean I\'m failing. My clothes fit better. I\'m getting stronger. The scale doesn\'t show muscle gain or other positive changes.',
                'outcome' => 'Talked to Coach Ken who reminded me about body recomposition. Took measurements and realized I lost 2 inches from waist. Refocused on non-scale victories.',
                'mood_before' => 4,
                'mood_after' => 7,
            ],
            [
                'title' => 'Grateful for supportive community',
                'situation' => 'Had a tough week and the group chat was incredibly supportive. Coach Ken and other members shared encouragement and their own struggles.',
                'thoughts' => 'I\'m not alone in this journey. Having this community makes such a huge difference. Their support means everything.',
                'emotions' => 'Grateful (9/10), Supported (9/10), Connected (8/10), Hopeful (8/10)',
                'behaviors' => 'Shared my own struggles honestly in group chat, offered support to another struggling member, committed to weekly check-ins',
                'alternative_thoughts' => 'Vulnerability is strength. Asking for help and receiving support is part of growth. I can both receive and give support.',
                'outcome' => 'Felt deeply connected to the community. Realized that my journey impacts others and their journeys impact mine. Renewed commitment to show up.',
                'mood_before' => 5,
                'mood_after' => 9,
            ],
        ];

        // Generate ~35 CBT entries over 3 months (~2-3 per week)
        for ($i = 0; $i < 35; $i++) {
            $entry = $entries[$i % count($entries)];
            $date = Carbon::now()->subDays(90 - ($i * 2.5));

            DB::table('cbt_journal_entries')->insert([
                'user_id' => $userId,
                'title' => $entry['title'],
                'situation' => $entry['situation'],
                'thoughts' => $entry['thoughts'],
                'emotions' => $entry['emotions'],
                'behaviors' => $entry['behaviors'],
                'alternative_thoughts' => $entry['alternative_thoughts'],
                'outcome' => $entry['outcome'],
                'mood_before' => $entry['mood_before'],
                'mood_after' => $entry['mood_after'],
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        echo "  ✅ Created 35 CBT journal entries\n";
    }

    private function generateMessages($coachId, $maleUserId, $femaleUserId)
    {
        echo "💬 Generating messages and group chat...\n";

        // Coach Ken <-> Male Demo User conversations
        $maleConversations = [
            ['from' => $coachId, 'to' => $maleUserId, 'message' => 'Hey John! Fantastic work on your workout today. That new PR on deadlifts was impressive! 💪', 'days_ago' => 1, 'hours' => 18],
            ['from' => $maleUserId, 'to' => $coachId, 'message' => 'Thanks Coach Ken! I really felt the difference with the form adjustments you suggested last week.', 'days_ago' => 1, 'hours' => 19],
            ['from' => $coachId, 'to' => $maleUserId, 'message' => 'That\'s exactly what I like to hear. Quality form leads to quality gains. Keep it up!', 'days_ago' => 1, 'hours' => 19],

            ['from' => $coachId, 'to' => $maleUserId, 'message' => 'I noticed your nutrition logging has been really consistent this week. How are you feeling energy-wise?', 'days_ago' => 3, 'hours' => 14],
            ['from' => $maleUserId, 'to' => $coachId, 'message' => 'Much better! Meal prepping on Sundays has been a total game changer. No more grabbing fast food.', 'days_ago' => 3, 'hours' => 15],
            ['from' => $coachId, 'to' => $maleUserId, 'message' => 'Excellent! That\'s one of the biggest factors in sustainable progress. Preparation beats willpower every time.', 'days_ago' => 3, 'hours' => 15],

            ['from' => $maleUserId, 'to' => $coachId, 'message' => 'Quick question - should I increase weight on squats next week or focus on adding more reps?', 'days_ago' => 5, 'hours' => 20],
            ['from' => $coachId, 'to' => $maleUserId, 'message' => 'Great question! Let\'s add 5lbs to the bar but drop to 6-7 reps. We\'ll build back up to 8 reps at the new weight.', 'days_ago' => 5, 'hours' => 21],
            ['from' => $maleUserId, 'to' => $coachId, 'message' => 'Perfect, that makes sense. Thanks!', 'days_ago' => 5, 'hours' => 21],
        ];

        foreach ($maleConversations as $msg) {
            $date = Carbon::now()->subDays($msg['days_ago'])->setTime($msg['hours'], rand(0, 59));
            DB::table('messages')->insert([
                'from_user_id' => $msg['from'],
                'to_user_id' => $msg['to'],
                'from_user_type' => ($msg['from'] == $coachId) ? 'coach' : 'client',
                'to_user_type' => ($msg['to'] == $coachId) ? 'coach' : 'client',
                'message' => $msg['message'],
                'is_read' => true,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        // Coach Ken <-> Female Demo User conversations
        $femaleConversations = [
            ['from' => $coachId, 'to' => $femaleUserId, 'message' => 'Sarah! Your cardio endurance has improved so much over the past month. Really proud of your progress! 🎉', 'days_ago' => 1, 'hours' => 17],
            ['from' => $femaleUserId, 'to' => $coachId, 'message' => 'Thank you Coach Ken! I can definitely feel the difference. That HIIT workout doesn\'t destroy me anymore 😅', 'days_ago' => 1, 'hours' => 18],
            ['from' => $coachId, 'to' => $femaleUserId, 'message' => 'Haha that\'s a great sign! Your body is adapting beautifully. Ready to level up?', 'days_ago' => 1, 'hours' => 18],

            ['from' => $femaleUserId, 'to' => $coachId, 'message' => 'I loved the new workout plan you sent! The variety keeps things interesting.', 'days_ago' => 4, 'hours' => 12],
            ['from' => $coachId, 'to' => $femaleUserId, 'message' => 'Glad you\'re enjoying it! Variety prevents plateaus and keeps you mentally engaged. Win-win!', 'days_ago' => 4, 'hours' => 13],

            ['from' => $coachId, 'to' => $femaleUserId, 'message' => 'Hey Sarah, I\'m adding some strength training to your plan next week. Time to build that lean muscle! 💪', 'days_ago' => 6, 'hours' => 16],
            ['from' => $femaleUserId, 'to' => $coachId, 'message' => 'Sounds great! I\'ve been wanting to try more weight training. Excited!', 'days_ago' => 6, 'hours' => 17],
            ['from' => $coachId, 'to' => $femaleUserId, 'message' => 'Perfect attitude! We\'ll start with compound movements 2x per week and build from there.', 'days_ago' => 6, 'hours' => 17],
        ];

        foreach ($femaleConversations as $msg) {
            $date = Carbon::now()->subDays($msg['days_ago'])->setTime($msg['hours'], rand(0, 59));
            DB::table('messages')->insert([
                'from_user_id' => $msg['from'],
                'to_user_id' => $msg['to'],
                'from_user_type' => ($msg['from'] == $coachId) ? 'coach' : 'client',
                'to_user_type' => ($msg['to'] == $coachId) ? 'coach' : 'client',
                'message' => $msg['message'],
                'is_read' => true,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        // Group chat messages (BodyF1rst community)
        $groupMessages = [
            ['from' => $coachId, 'user_type' => 'coach', 'message' => 'Great work this week, team! Who\'s ready for the new 30-day transformation challenge? 🏆', 'days_ago' => 2, 'minutes' => 0],
            ['from' => $maleUserId, 'user_type' => 'client', 'message' => 'I\'m definitely in! What\'s the challenge exactly?', 'days_ago' => 2, 'minutes' => 8],
            ['from' => $femaleUserId, 'user_type' => 'client', 'message' => 'Count me in too! Love a good challenge 🔥', 'days_ago' => 2, 'minutes' => 12],
            ['from' => $coachId, 'user_type' => 'coach', 'message' => '30 consecutive days of: ✅ Complete scheduled workout ✅ Log all meals ✅ Drink 8 glasses of water ✅ Journal thoughts/feelings', 'days_ago' => 2, 'minutes' => 15],
            ['from' => $maleUserId, 'user_type' => 'client', 'message' => 'Challenge accepted! The accountability will be awesome 💪', 'days_ago' => 2, 'minutes' => 20],
            ['from' => $femaleUserId, 'user_type' => 'client', 'message' => 'This is exactly what I need right now. Let\'s do this together!', 'days_ago' => 2, 'minutes' => 23],
            ['from' => $coachId, 'user_type' => 'coach', 'message' => 'Love the energy! Remember, we\'re all in this together. Support each other!', 'days_ago' => 2, 'minutes' => 27],

            ['from' => $maleUserId, 'user_type' => 'client', 'message' => 'Hit a new PR on bench press today! Feeling strong 😤', 'days_ago' => 5, 'minutes' => 0],
            ['from' => $femaleUserId, 'user_type' => 'client', 'message' => 'Amazing John!! Congrats! 🎉', 'days_ago' => 5, 'minutes' => 10],
            ['from' => $coachId, 'user_type' => 'coach', 'message' => 'That\'s what I\'m talking about! Consistency pays off. Proud of you!', 'days_ago' => 5, 'minutes' => 15],

            ['from' => $femaleUserId, 'user_type' => 'client', 'message' => 'Anyone else meal prepping this weekend? Would love some recipe ideas!', 'days_ago' => 7, 'minutes' => 0],
            ['from' => $maleUserId, 'user_type' => 'client', 'message' => 'Yes! I\'m doing chicken and rice bowls, overnight oats, and egg muffins', 'days_ago' => 7, 'minutes' => 25],
            ['from' => $coachId, 'user_type' => 'coach', 'message' => 'Great choices! I\'ll share my favorite meal prep guide in the resources section 📚', 'days_ago' => 7, 'minutes' => 35],
        ];

        foreach ($groupMessages as $msg) {
            $date = Carbon::now()->subDays($msg['days_ago'])->addMinutes($msg['minutes']);
            DB::table('group_messages')->insert([
                'user_id' => $msg['from'],
                'user_type' => $msg['user_type'],
                'message' => $msg['message'],
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        echo "  ✅ Created " . count($maleConversations) . " messages with John\n";
        echo "  ✅ Created " . count($femaleConversations) . " messages with Sarah\n";
        echo "  ✅ Created " . count($groupMessages) . " group chat messages\n";
    }

    private function generateChallengesAndAchievements($maleUserId, $femaleUserId)
    {
        echo "🏆 Generating challenges and achievements...\n";

        // Create active challenge
        $challengeId = DB::table('challenges')->insertGetId([
            'name' => '30-Day Transformation Challenge',
            'description' => 'Complete 30 consecutive days of workouts, meal logging, hydration tracking, and daily journaling. Stay committed, stay consistent, achieve transformation!',
            'start_date' => Carbon::now()->subDays(15),
            'end_date' => Carbon::now()->addDays(15),
            'points_reward' => 500,
            'is_active' => true,
            'created_at' => Carbon::now()->subDays(20),
            'updated_at' => now(),
        ]);

        // Enroll both demo users
        foreach ([$maleUserId => 75, $femaleUserId => 82] as $userId => $progress) {
            DB::table('challenge_participants')->insert([
                'challenge_id' => $challengeId,
                'user_id' => $userId,
                'progress' => $progress,
                'is_completed' => false,
                'joined_at' => Carbon::now()->subDays(15),
                'created_at' => Carbon::now()->subDays(15),
                'updated_at' => now(),
            ]);
        }

        // Create achievements
        $achievements = [
            ['name' => 'First Workout', 'description' => 'Complete your very first workout', 'icon' => '💪', 'points' => 50, 'category' => 'workout'],
            ['name' => 'Week Warrior', 'description' => 'Log workouts for 7 consecutive days', 'icon' => '🔥', 'points' => 100, 'category' => 'workout'],
            ['name' => 'Month Master', 'description' => 'Maintain a 30-day workout streak', 'icon' => '⭐', 'points' => 250, 'category' => 'workout'],
            ['name' => 'Nutrition Novice', 'description' => 'Log your first meal', 'icon' => '🍎', 'points' => 50, 'category' => 'nutrition'],
            ['name' => 'Meal Maestro', 'description' => 'Log meals for 30 consecutive days', 'icon' => '🥗', 'points' => 200, 'category' => 'nutrition'],
            ['name' => 'Consistency King/Queen', 'description' => 'Maintain a 30-day overall streak', 'icon' => '👑', 'points' => 300, 'category' => 'streak'],
            ['name' => 'Goal Getter', 'description' => 'Reach your goal weight', 'icon' => '🎯', 'points' => 500, 'category' => 'milestone'],
            ['name' => 'Strength Seeker', 'description' => 'Complete 50 strength training workouts', 'icon' => '🏋️', 'points' => 400, 'category' => 'workout'],
            ['name' => 'Cardio Champion', 'description' => 'Complete 25 cardio workouts', 'icon' => '🏃', 'points' => 300, 'category' => 'workout'],
            ['name' => 'Mindful Maven', 'description' => 'Complete 20 CBT journal entries', 'icon' => '🧠', 'points' => 250, 'category' => 'wellness'],
        ];

        $achievementIds = [];
        foreach ($achievements as $achievement) {
            $achId = DB::table('achievements')->insertGetId([
                'name' => $achievement['name'],
                'description' => $achievement['description'],
                'icon' => $achievement['icon'],
                'category' => $achievement['category'],
                'points_reward' => $achievement['points'],
                'created_at' => Carbon::now()->subMonths(6),
                'updated_at' => Carbon::now()->subMonths(6),
            ]);
            $achievementIds[] = $achId;
        }

        // Award achievements to users (male user gets 6, female gets 7)
        $maleAchievements = array_slice($achievementIds, 0, 6);
        $femaleAchievements = array_slice($achievementIds, 0, 7);

        foreach ($maleAchievements as $index => $achId) {
            DB::table('user_achievements')->insert([
                'user_id' => $maleUserId,
                'achievement_id' => $achId,
                'earned_at' => Carbon::now()->subDays(80 - ($index * 10)),
                'created_at' => Carbon::now()->subDays(80 - ($index * 10)),
                'updated_at' => now(),
            ]);
        }

        foreach ($femaleAchievements as $index => $achId) {
            DB::table('user_achievements')->insert([
                'user_id' => $femaleUserId,
                'achievement_id' => $achId,
                'earned_at' => Carbon::now()->subDays(85 - ($index * 12)),
                'created_at' => Carbon::now()->subDays(85 - ($index * 12)),
                'updated_at' => now(),
            ]);
        }

        echo "  ✅ Created 1 active challenge\n";
        echo "  ✅ Created 10 achievements\n";
        echo "  ✅ Awarded 6 achievements to John, 7 to Sarah\n";
    }

    private function generateProgressData($userId, $isMale)
    {
        echo "📊 Generating progress tracking data for user $userId...\n";

        // Generate weekly body measurements over 3 months (12 weeks)
        $startWeight = $isMale ? 90 : 72; // Starting weights in kg
        $currentWeight = $isMale ? 85 : 68; // Current weights in kg
        $weightLossPerWeek = ($startWeight - $currentWeight) / 12;

        for ($week = 0; $week < 13; $week++) {
            $date = Carbon::now()->subWeeks(12 - $week);
            $progressWeight = $startWeight - ($weightLossPerWeek * $week);

            DB::table('body_measurements')->insert([
                'user_id' => $userId,
                'weight' => round($progressWeight, 1),
                'body_fat_percentage' => $isMale ? round(22 - ($week * 0.35), 1) : round(28 - ($week * 0.45), 1),
                'muscle_mass' => $isMale ? round(32 + ($week * 0.25), 1) : round(25 + ($week * 0.18), 1),
                'chest' => $isMale ? round(100 - ($week * 0.3), 1) : null,
                'waist' => $isMale ? round(92 - ($week * 0.5), 1) : round(75 - ($week * 0.4), 1),
                'hips' => !$isMale ? round(95 - ($week * 0.3), 1) : null,
                'arms' => $isMale ? round(36 + ($week * 0.12), 1) : round(28 + ($week * 0.08), 1),
                'thighs' => $isMale ? round(58 - ($week * 0.15), 1) : round(54 - ($week * 0.12), 1),
                'measured_at' => $date,
                'notes' => $week % 3 == 0 ? 'Feeling great! Noticing visible progress' : null,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        // Create or update gamification stats
        $existingStats = DB::table('gamification_stats')->where('user_id', $userId)->first();

        if ($existingStats) {
            DB::table('gamification_stats')->where('user_id', $userId)->update([
                'total_points' => $isMale ? 2850 : 3200,
                'level' => $isMale ? 8 : 9,
                'streak_days' => $isMale ? 45 : 52,
                'workouts_completed' => 40,
                'meals_logged' => 360,
                'achievements_unlocked' => $isMale ? 6 : 7,
                'challenges_completed' => 0,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('gamification_stats')->insert([
                'user_id' => $userId,
                'total_points' => $isMale ? 2850 : 3200,
                'level' => $isMale ? 8 : 9,
                'streak_days' => $isMale ? 45 : 52,
                'workouts_completed' => 40,
                'meals_logged' => 360,
                'achievements_unlocked' => $isMale ? 6 : 7,
                'challenges_completed' => 0,
                'created_at' => Carbon::now()->subMonths(3),
                'updated_at' => now(),
            ]);
        }

        echo "  ✅ Created 13 weeks of body measurements\n";
        echo "  ✅ Updated gamification stats\n";
    }

    private function getRandomWorkoutNote()
    {
        $notes = [
            'Great workout! Felt strong throughout.',
            'Pushed hard today. Feeling accomplished!',
            'Form was on point. Ready to increase weight next time.',
            'Challenging but doable. Love the progress!',
            'Energy was high. Best workout this week!',
            'Tough session but finished strong.',
            'Felt the burn! In a good way.',
            'Solid workout. Consistency is key.',
        ];
        return $notes[array_rand($notes)];
    }

    private function getRandomExerciseNote()
    {
        $notes = [
            'Good form maintained throughout',
            'Felt strong on this one',
            'Could go heavier next time',
            'Perfect tempo and control',
            'Challenging but completed',
            'Focused on mind-muscle connection',
            'Full range of motion',
            'Steady progression',
        ];
        return $notes[array_rand($notes)];
    }
}
