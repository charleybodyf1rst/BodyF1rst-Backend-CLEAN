<?php

namespace App\Services;

use App\Models\Workout;
use App\Models\Exercise;
use App\Models\WorkoutExercise;
use App\Models\WorkoutEquipment;
use App\Models\ExerciseWarmup;
use App\Models\ExerciseCooldown;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorkoutAIService
{
    private $openaiApiKey;
    private $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->openaiApiKey = env('OPENAI_API_KEY');
    }

    /**
     * Generate a complete workout using AI based on user preferences
     */
    public function generateWorkout($parameters)
    {
        try {
            $prompt = $this->buildWorkoutPrompt($parameters);
            $aiResponse = $this->callOpenAI($prompt);
            
            if ($aiResponse && isset($aiResponse['choices'][0]['message']['content'])) {
                $workoutData = $this->parseAIResponse($aiResponse['choices'][0]['message']['content']);
                return $this->createWorkoutFromAI($workoutData, $parameters);
            }

            return ['success' => false, 'message' => 'Failed to generate workout'];
        } catch (\Exception $e) {
            Log::error('AI Workout Generation Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error generating workout: ' . $e->getMessage()];
        }
    }

    /**
     * Build the AI prompt for workout generation
     */
    private function buildWorkoutPrompt($parameters)
    {
        $fitnessLevel = $parameters['fitness_level'] ?? 'intermediate';
        $duration = $parameters['duration'] ?? 45;
        $targetAreas = $parameters['target_areas'] ?? ['full body'];
        $equipment = $parameters['equipment'] ?? ['bodyweight'];
        $workoutType = $parameters['workout_type'] ?? 'strength';
        $goals = $parameters['goals'] ?? 'general fitness';

        return "Create a detailed {$duration}-minute {$workoutType} workout for a {$fitnessLevel} level person.

Target Areas: " . implode(', ', $targetAreas) . "
Available Equipment: " . implode(', ', $equipment) . "
Fitness Goals: {$goals}

Please provide the response in the following JSON format:
{
    \"workout_name\": \"Creative workout name\",
    \"workout_definition\": \"Brief description of what this workout accomplishes\",
    \"intensity_level\": \"low/medium/high\",
    \"estimated_duration\": {$duration},
    \"target_areas\": \"comma-separated list\",
    \"warmup_exercises\": [
        {
            \"name\": \"Exercise name\",
            \"description\": \"How to perform\",
            \"duration\": \"time in seconds\",
            \"reps\": \"number or null\",
            \"order\": 1
        }
    ],
    \"main_exercises\": [
        {
            \"name\": \"Exercise name\",
            \"description\": \"Detailed instructions\",
            \"sets\": 3,
            \"reps\": \"12-15\",
            \"rest_time\": \"60 seconds\",
            \"order\": 1,
            \"muscle_groups\": \"primary,secondary\",
            \"equipment_needed\": \"equipment name or bodyweight\"
        }
    ],
    \"cooldown_exercises\": [
        {
            \"name\": \"Exercise name\",
            \"description\": \"How to perform\",
            \"duration\": \"time in seconds\",
            \"reps\": \"number or null\",
            \"order\": 1
        }
    ],
    \"equipment_list\": [
        {
            \"name\": \"Equipment name\",
            \"required\": true,
            \"description\": \"Brief description\"
        }
    ]
}

Make sure the workout is safe, progressive, and appropriate for the specified fitness level. Include 3-5 warmup exercises, 6-10 main exercises, and 3-5 cooldown exercises.";
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI($prompt)
    {
        if (!$this->openaiApiKey) {
            throw new \Exception('OpenAI API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->baseUrl . '/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a certified personal trainer and fitness expert. Create safe, effective workouts based on user requirements. Always respond with valid JSON format.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.7
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('OpenAI API request failed: ' . $response->body());
    }

    /**
     * Parse AI response and extract workout data
     */
    private function parseAIResponse($content)
    {
        // Clean the response to extract JSON
        $content = trim($content);
        
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        
        $workoutData = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from AI: ' . json_last_error_msg());
        }

        return $workoutData;
    }

    /**
     * Create workout in database from AI-generated data
     */
    private function createWorkoutFromAI($workoutData, $parameters)
    {
        try {
            // Create the main workout
            $workout = Workout::create([
                'name' => $workoutData['workout_name'],
                'workout_definition' => $workoutData['workout_definition'],
                'intensity_level' => $workoutData['intensity_level'],
                'estimated_duration' => $workoutData['estimated_duration'],
                'target_areas' => $workoutData['target_areas'],
                'type' => $parameters['workout_type'] ?? 'strength',
                'created_by' => $parameters['created_by'] ?? null,
                'is_ai_generated' => true
            ]);

            // Add equipment
            if (isset($workoutData['equipment_list'])) {
                foreach ($workoutData['equipment_list'] as $equipment) {
                    WorkoutEquipment::create([
                        'workout_id' => $workout->id,
                        'equipment_name' => $equipment['name'],
                        'description' => $equipment['description'] ?? '',
                        'required' => $equipment['required'] ?? true,
                        'icon' => $this->getEquipmentIcon($equipment['name'])
                    ]);
                }
            }

            // Add main exercises
            if (isset($workoutData['main_exercises'])) {
                foreach ($workoutData['main_exercises'] as $index => $exerciseData) {
                    // Create or find exercise
                    $exercise = Exercise::firstOrCreate([
                        'name' => $exerciseData['name']
                    ], [
                        'description' => $exerciseData['description'],
                        'muscle_groups' => $exerciseData['muscle_groups'] ?? '',
                        'equipment' => $exerciseData['equipment_needed'] ?? 'bodyweight'
                    ]);

                    // Link exercise to workout
                    WorkoutExercise::create([
                        'workout_id' => $workout->id,
                        'exercise_id' => $exercise->id,
                        'sets' => $exerciseData['sets'] ?? 1,
                        'reps' => $exerciseData['reps'] ?? '',
                        'rest_time' => $exerciseData['rest_time'] ?? '60 seconds',
                        'order' => $exerciseData['order'] ?? $index + 1
                    ]);
                }
            }

            // Add warmup exercises
            if (isset($workoutData['warmup_exercises'])) {
                $this->addWarmupExercises($workout->id, $workoutData['warmup_exercises']);
            }

            // Add cooldown exercises
            if (isset($workoutData['cooldown_exercises'])) {
                $this->addCooldownExercises($workout->id, $workoutData['cooldown_exercises']);
            }

            return [
                'success' => true,
                'workout_id' => $workout->id,
                'workout' => $workout->load(['exercises', 'equipment'])
            ];

        } catch (\Exception $e) {
            Log::error('Error creating AI workout: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating workout: ' . $e->getMessage()];
        }
    }

    /**
     * Add warmup exercises for the workout
     */
    private function addWarmupExercises($workoutId, $warmupExercises)
    {
        // Get first exercise from the workout to associate warmups
        $firstExercise = WorkoutExercise::where('workout_id', $workoutId)
            ->orderBy('order')
            ->first();

        if ($firstExercise) {
            foreach ($warmupExercises as $warmup) {
                ExerciseWarmup::create([
                    'exercise_id' => $firstExercise->exercise_id,
                    'name' => $warmup['name'],
                    'description' => $warmup['description'],
                    'duration' => $warmup['duration'] ?? null,
                    'reps' => $warmup['reps'] ?? null,
                    'order' => $warmup['order'] ?? 1
                ]);
            }
        }
    }

    /**
     * Add cooldown exercises for the workout
     */
    private function addCooldownExercises($workoutId, $cooldownExercises)
    {
        // Get last exercise from the workout to associate cooldowns
        $lastExercise = WorkoutExercise::where('workout_id', $workoutId)
            ->orderBy('order', 'desc')
            ->first();

        if ($lastExercise) {
            foreach ($cooldownExercises as $cooldown) {
                ExerciseCooldown::create([
                    'exercise_id' => $lastExercise->exercise_id,
                    'name' => $cooldown['name'],
                    'description' => $cooldown['description'],
                    'duration' => $cooldown['duration'] ?? null,
                    'reps' => $cooldown['reps'] ?? null,
                    'order' => $cooldown['order'] ?? 1
                ]);
            }
        }
    }

    /**
     * Get appropriate icon for equipment
     */
    private function getEquipmentIcon($equipmentName)
    {
        $iconMap = [
            'dumbbells' => 'fitness-outline',
            'barbell' => 'barbell-outline',
            'kettlebell' => 'fitness-outline',
            'resistance bands' => 'fitness-outline',
            'yoga mat' => 'body-outline',
            'bench' => 'fitness-outline',
            'pull-up bar' => 'fitness-outline',
            'bodyweight' => 'body-outline'
        ];

        $lowerName = strtolower($equipmentName);
        foreach ($iconMap as $equipment => $icon) {
            if (strpos($lowerName, $equipment) !== false) {
                return $icon;
            }
        }

        return 'fitness-outline';
    }

    /**
     * Generate workout suggestions based on user history
     */
    public function generateWorkoutSuggestions($userId, $limit = 5)
    {
        try {
            // Get user's workout history and preferences
            $userHistory = $this->getUserWorkoutHistory($userId);
            $suggestions = [];

            // Generate different types of workouts
            $workoutTypes = ['strength', 'cardio', 'flexibility', 'hiit'];
            $intensityLevels = ['low', 'medium', 'high'];

            foreach ($workoutTypes as $type) {
                if (count($suggestions) >= $limit) break;

                $parameters = [
                    'workout_type' => $type,
                    'fitness_level' => $userHistory['fitness_level'] ?? 'intermediate',
                    'duration' => rand(30, 60),
                    'intensity_level' => $intensityLevels[array_rand($intensityLevels)],
                    'target_areas' => $this->getRandomTargetAreas(),
                    'equipment' => $userHistory['preferred_equipment'] ?? ['bodyweight'],
                    'created_by' => null
                ];

                $result = $this->generateWorkout($parameters);
                if ($result['success']) {
                    $suggestions[] = $result['workout'];
                }
            }

            return ['success' => true, 'suggestions' => $suggestions];

        } catch (\Exception $e) {
            Log::error('Error generating workout suggestions: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error generating suggestions'];
        }
    }

    /**
     * Get user workout history for personalization
     */
    private function getUserWorkoutHistory($userId)
    {
        // This would typically query user's workout history
        // For now, return default preferences
        return [
            'fitness_level' => 'intermediate',
            'preferred_equipment' => ['bodyweight', 'dumbbells'],
            'preferred_duration' => 45,
            'preferred_intensity' => 'medium'
        ];
    }

    /**
     * Get random target areas for variety
     */
    private function getRandomTargetAreas()
    {
        $allAreas = ['chest', 'back', 'shoulders', 'arms', 'legs', 'core', 'glutes'];
        $numAreas = rand(2, 4);
        return array_slice(array_shuffle($allAreas), 0, $numAreas);
    }
}
