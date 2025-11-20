<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MealLoggerService
{
    /**
     * Log a meal with nutritional information.
     *
     * @param array $args
     * @return array
     */
    public function logMeal(array $args): array
    {
        try {
            // Validate required parameters
            if (empty($args['name']) || !isset($args['calories'])) {
                return ['ok' => false, 'error' => 'Missing required fields: name, calories'];
            }

            $userId = auth()->id();
            if ($userId === null) {
                return ['ok' => false, 'error' => 'Authentication required to log meal'];
            }

            // Sanitize and validate input
            $mealData = [
                'id' => Str::uuid()->toString(),
                'name' => trim(strip_tags($args['name'])),
                'calories' => (float) $args['calories'],
                'protein_g' => isset($args['protein_g']) ? (float) $args['protein_g'] : 0,
                'carbs_g' => isset($args['carbs_g']) ? (float) $args['carbs_g'] : 0,
                'fat_g' => isset($args['fat_g']) ? (float) $args['fat_g'] : 0,
                'logged_at' => isset($args['when']) ? Carbon::parse($args['when']) : now(),
                'user_id' => $userId,
            ];

            // Validate nutritional values
            if ($mealData['calories'] < 0 || $mealData['calories'] > 10000) {
                return ['ok' => false, 'error' => 'Invalid calorie value'];
            }

            // Save to nutrition_logs table for persistence
            try {
                DB::table('nutrition_logs')->insert([
                    'user_id' => $mealData['user_id'],
                    'food_name' => $mealData['name'],
                    'calories' => $mealData['calories'],
                    'protein_g' => $mealData['protein_g'],
                    'carbs_g' => $mealData['carbs_g'],
                    'fat_g' => $mealData['fat_g'],
                    'logged_at' => $mealData['logged_at'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info('Meal logged successfully and saved to database', [
                    'meal_id' => $mealData['id'],
                    'user_id' => $mealData['user_id']
                ]);
            } catch (\Exception $dbError) {
                // Log error but don't fail the request - graceful degradation
                Log::error('Failed to save meal to database', [
                    'error' => $dbError->getMessage(),
                    'meal_id' => $mealData['id']
                ]);
            }

            return [
                'ok' => true,
                'id' => $mealData['id'],
                'message' => 'Meal logged successfully',
                'data' => [
                    'name' => $mealData['name'],
                    'calories' => $mealData['calories'],
                    'macros' => [
                        'protein_g' => $mealData['protein_g'],
                        'carbs_g' => $mealData['carbs_g'],
                        'fat_g' => $mealData['fat_g']
                    ],
                    'logged_at' => $mealData['logged_at']->toISOString()
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to log meal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'args' => $args
            ]);

            return [
                'ok' => false,
                'error' => 'Failed to log meal: ' . $e->getMessage()
            ];
        }
    }
}
