<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Nutrition Care Process (NCP) Algorithm Service
 *
 * Calculates personalized nutrition requirements using:
 * - BMR (Basal Metabolic Rate) via Mifflin-St Jeor formula
 * - TDEE (Total Daily Energy Expenditure) with activity multipliers
 * - Macro distribution based on fitness goals
 * - Body composition adjustments
 */
class NutritionCalculationService
{
    /**
     * Activity level multipliers for TDEE calculation
     */
    const ACTIVITY_MULTIPLIERS = [
        'sedentary' => 1.2,           // Little to no exercise
        'lightly_active' => 1.375,    // Light exercise 1-3 days/week
        'moderately_active' => 1.55,  // Moderate exercise 3-5 days/week
        'very_active' => 1.725,       // Hard exercise 6-7 days/week
        'extremely_active' => 1.9     // Physical job + exercise or athlete
    ];

    /**
     * Goal-based calorie adjustments (from TDEE)
     */
    const GOAL_ADJUSTMENTS = [
        'weight_loss_aggressive' => -0.25,  // 25% deficit
        'weight_loss_moderate' => -0.20,    // 20% deficit
        'weight_loss_mild' => -0.15,        // 15% deficit
        'maintenance' => 0.0,               // No adjustment
        'muscle_gain_mild' => 0.10,         // 10% surplus
        'muscle_gain_moderate' => 0.15,     // 15% surplus
        'muscle_gain_aggressive' => 0.20    // 20% surplus
    ];

    /**
     * Goal-based macro ratios (P:C:F percentages)
     */
    const MACRO_RATIOS = [
        'weight_loss' => ['protein' => 0.35, 'carbs' => 0.35, 'fat' => 0.30],
        'muscle_gain' => ['protein' => 0.30, 'carbs' => 0.45, 'fat' => 0.25],
        'maintenance' => ['protein' => 0.30, 'carbs' => 0.40, 'fat' => 0.30],
        'performance' => ['protein' => 0.25, 'carbs' => 0.50, 'fat' => 0.25],
        'health' => ['protein' => 0.25, 'carbs' => 0.45, 'fat' => 0.30],
        'keto' => ['protein' => 0.20, 'carbs' => 0.05, 'fat' => 0.75],
        'low_carb' => ['protein' => 0.30, 'carbs' => 0.20, 'fat' => 0.50]
    ];

    /**
     * Calculate personalized nutrition profile for a user
     *
     * @param array $userData User data (age, gender, weight, height, activity_level, goal)
     * @return array Complete nutrition profile
     */
    public function calculateNutritionProfile(array $userData): array
    {
        // Validate required fields
        $this->validateUserData($userData);

        // Step 1: Calculate BMR (Basal Metabolic Rate)
        $bmr = $this->calculateBMR(
            $userData['weight_lbs'],
            $userData['height_inches'],
            $userData['age'],
            $userData['gender']
        );

        // Step 2: Calculate TDEE (Total Daily Energy Expenditure)
        $tdee = $this->calculateTDEE($bmr, $userData['activity_level']);

        // Step 3: Adjust calories based on goal
        $targetCalories = $this->adjustCaloriesForGoal($tdee, $userData['goal']);

        // Step 4: Calculate macro distribution
        $macros = $this->calculateMacros($targetCalories, $userData['goal'], $userData['weight_lbs']);

        // Step 5: Calculate meal timing recommendations
        $mealTiming = $this->calculateMealTiming($targetCalories, $userData['meals_per_day'] ?? 3);

        // Step 6: Calculate micronutrient requirements
        $micros = $this->calculateMicronutrients($userData['gender'], $userData['age'], $userData['weight_lbs']);

        // Step 7: Calculate hydration needs
        $hydration = $this->calculateHydration($userData['weight_lbs'], $userData['activity_level']);

        return [
            'bmr' => round($bmr),
            'tdee' => round($tdee),
            'target_calories' => round($targetCalories),
            'macros' => $macros,
            'meal_timing' => $mealTiming,
            'micronutrients' => $micros,
            'hydration' => $hydration,
            'calculated_at' => now()->toDateTimeString(),
            'recommendations' => $this->generateRecommendations($userData, $targetCalories, $macros)
        ];
    }

    /**
     * Calculate BMR using Mifflin-St Jeor formula (most accurate)
     *
     * Men: BMR = (10 × weight_kg) + (6.25 × height_cm) - (5 × age) + 5
     * Women: BMR = (10 × weight_kg) + (6.25 × height_cm) - (5 × age) - 161
     *
     * @param float $weightLbs Weight in pounds
     * @param float $heightInches Height in inches
     * @param int $age Age in years
     * @param string $gender 'male' or 'female'
     * @return float BMR in calories
     */
    private function calculateBMR(float $weightLbs, float $heightInches, int $age, string $gender): float
    {
        // Convert to metric
        $weightKg = $weightLbs * 0.453592;
        $heightCm = $heightInches * 2.54;

        // Mifflin-St Jeor formula
        $bmr = (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age);

        if (strtolower($gender) === 'male') {
            $bmr += 5;
        } else {
            $bmr -= 161;
        }

        return $bmr;
    }

    /**
     * Calculate TDEE by applying activity multiplier to BMR
     *
     * @param float $bmr Basal Metabolic Rate
     * @param string $activityLevel Activity level key
     * @return float TDEE in calories
     */
    private function calculateTDEE(float $bmr, string $activityLevel): float
    {
        $multiplier = self::ACTIVITY_MULTIPLIERS[$activityLevel] ?? self::ACTIVITY_MULTIPLIERS['sedentary'];
        return $bmr * $multiplier;
    }

    /**
     * Adjust calories based on fitness goal
     *
     * @param float $tdee Total Daily Energy Expenditure
     * @param string $goal Fitness goal
     * @return float Adjusted target calories
     */
    private function adjustCaloriesForGoal(float $tdee, string $goal): float
    {
        $goalKey = $this->mapGoalToAdjustmentKey($goal);
        $adjustment = self::GOAL_ADJUSTMENTS[$goalKey] ?? 0;

        return $tdee * (1 + $adjustment);
    }

    /**
     * Calculate macro distribution based on goal and body weight
     *
     * @param float $calories Target daily calories
     * @param string $goal Fitness goal
     * @param float $weightLbs Body weight in pounds
     * @return array Macro breakdown
     */
    private function calculateMacros(float $calories, string $goal, float $weightLbs): array
    {
        // Get macro ratios for goal
        $goalKey = $this->mapGoalToMacroKey($goal);
        $ratios = self::MACRO_RATIOS[$goalKey] ?? self::MACRO_RATIOS['maintenance'];

        // Calculate grams based on calories
        // Protein: 4 cal/g, Carbs: 4 cal/g, Fat: 9 cal/g
        $proteinG = ($calories * $ratios['protein']) / 4;
        $carbsG = ($calories * $ratios['carbs']) / 4;
        $fatG = ($calories * $ratios['fat']) / 9;

        // Ensure minimum protein (0.8g per lb body weight for health, 1g for athletes)
        $minProtein = $weightLbs * (in_array($goal, ['muscle_gain', 'performance']) ? 1.0 : 0.8);
        $proteinG = max($proteinG, $minProtein);

        return [
            'protein_g' => round($proteinG, 1),
            'carbs_g' => round($carbsG, 1),
            'fat_g' => round($fatG, 1),
            'protein_percentage' => round($ratios['protein'] * 100),
            'carbs_percentage' => round($ratios['carbs'] * 100),
            'fat_percentage' => round($ratios['fat'] * 100),
            'protein_calories' => round($proteinG * 4),
            'carbs_calories' => round($carbsG * 4),
            'fat_calories' => round($fatG * 9)
        ];
    }

    /**
     * Calculate optimal meal timing and calorie distribution
     *
     * @param float $totalCalories Total daily calories
     * @param int $mealsPerDay Number of meals
     * @return array Meal timing recommendations
     */
    private function calculateMealTiming(float $totalCalories, int $mealsPerDay): array
    {
        $mealsPerDay = max(3, min(6, $mealsPerDay)); // Between 3-6 meals

        $timing = [];

        switch ($mealsPerDay) {
            case 3:
                $timing = [
                    ['meal' => 'Breakfast', 'time' => '7-8 AM', 'calories' => round($totalCalories * 0.30), 'percentage' => 30],
                    ['meal' => 'Lunch', 'time' => '12-1 PM', 'calories' => round($totalCalories * 0.40), 'percentage' => 40],
                    ['meal' => 'Dinner', 'time' => '6-7 PM', 'calories' => round($totalCalories * 0.30), 'percentage' => 30],
                ];
                break;
            case 4:
                $timing = [
                    ['meal' => 'Breakfast', 'time' => '7-8 AM', 'calories' => round($totalCalories * 0.25), 'percentage' => 25],
                    ['meal' => 'Lunch', 'time' => '12-1 PM', 'calories' => round($totalCalories * 0.30), 'percentage' => 30],
                    ['meal' => 'Snack', 'time' => '3-4 PM', 'calories' => round($totalCalories * 0.15), 'percentage' => 15],
                    ['meal' => 'Dinner', 'time' => '6-7 PM', 'calories' => round($totalCalories * 0.30), 'percentage' => 30],
                ];
                break;
            case 5:
                $timing = [
                    ['meal' => 'Breakfast', 'time' => '7-8 AM', 'calories' => round($totalCalories * 0.25), 'percentage' => 25],
                    ['meal' => 'Snack 1', 'time' => '10-11 AM', 'calories' => round($totalCalories * 0.10), 'percentage' => 10],
                    ['meal' => 'Lunch', 'time' => '12-1 PM', 'calories' => round($totalCalories * 0.30), 'percentage' => 30],
                    ['meal' => 'Snack 2', 'time' => '3-4 PM', 'calories' => round($totalCalories * 0.10), 'percentage' => 10],
                    ['meal' => 'Dinner', 'time' => '6-7 PM', 'calories' => round($totalCalories * 0.25), 'percentage' => 25],
                ];
                break;
            case 6:
                $timing = [
                    ['meal' => 'Breakfast', 'time' => '7-8 AM', 'calories' => round($totalCalories * 0.20), 'percentage' => 20],
                    ['meal' => 'Snack 1', 'time' => '10-11 AM', 'calories' => round($totalCalories * 0.15), 'percentage' => 15],
                    ['meal' => 'Lunch', 'time' => '12-1 PM', 'calories' => round($totalCalories * 0.25), 'percentage' => 25],
                    ['meal' => 'Snack 2', 'time' => '3-4 PM', 'calories' => round($totalCalories * 0.10), 'percentage' => 10],
                    ['meal' => 'Dinner', 'time' => '6-7 PM', 'calories' => round($totalCalories * 0.20), 'percentage' => 20],
                    ['meal' => 'Snack 3', 'time' => '9-10 PM', 'calories' => round($totalCalories * 0.10), 'percentage' => 10],
                ];
                break;
        }

        return [
            'meals_per_day' => $mealsPerDay,
            'meals' => $timing,
            'hours_between_meals' => round(16 / $mealsPerDay, 1) // Assuming 16-hour eating window
        ];
    }

    /**
     * Calculate micronutrient requirements
     *
     * @param string $gender
     * @param int $age
     * @param float $weightLbs
     * @return array Micronutrient recommendations
     */
    private function calculateMicronutrients(string $gender, int $age, float $weightLbs): array
    {
        $isMale = strtolower($gender) === 'male';

        return [
            'vitamins' => [
                'vitamin_a' => $isMale ? 900 : 700, // mcg
                'vitamin_c' => $isMale ? 90 : 75, // mg
                'vitamin_d' => 600, // IU (15 mcg)
                'vitamin_e' => 15, // mg
                'vitamin_k' => $isMale ? 120 : 90, // mcg
                'vitamin_b12' => 2.4, // mcg
                'folate' => 400, // mcg
            ],
            'minerals' => [
                'calcium' => $age > 50 ? 1200 : 1000, // mg
                'iron' => $isMale ? 8 : ($age > 50 ? 8 : 18), // mg
                'magnesium' => $isMale ? 420 : 320, // mg
                'zinc' => $isMale ? 11 : 8, // mg
                'potassium' => 4700, // mg
                'sodium' => 2300, // mg (max)
            ],
            'other' => [
                'fiber' => $isMale ? 38 : 25, // g
                'omega_3' => 1600, // mg EPA+DHA combined
            ]
        ];
    }

    /**
     * Calculate daily hydration needs
     *
     * @param float $weightLbs Body weight in pounds
     * @param string $activityLevel Activity level
     * @return array Hydration recommendations
     */
    private function calculateHydration(float $weightLbs, string $activityLevel): array
    {
        // Base: 0.5 oz per lb body weight
        $baseOz = $weightLbs * 0.5;

        // Activity adjustment
        $activityMultiplier = match($activityLevel) {
            'sedentary' => 1.0,
            'lightly_active' => 1.1,
            'moderately_active' => 1.25,
            'very_active' => 1.4,
            'extremely_active' => 1.6,
            default => 1.0
        };

        $totalOz = $baseOz * $activityMultiplier;
        $totalMl = $totalOz * 29.5735; // Convert to ml
        $totalCups = $totalOz / 8; // 8 oz per cup

        return [
            'ounces' => round($totalOz),
            'milliliters' => round($totalMl),
            'cups' => round($totalCups, 1),
            'liters' => round($totalMl / 1000, 2),
            'recommendation' => 'Drink ' . round($totalCups) . ' cups (8oz each) of water daily. Increase during exercise.',
            'timing' => [
                'Upon waking' => '16 oz',
                'Before each meal' => '8 oz',
                'During workout' => '8-16 oz per hour',
                'Before bed' => '8 oz'
            ]
        ];
    }

    /**
     * Generate personalized recommendations
     *
     * @param array $userData
     * @param float $targetCalories
     * @param array $macros
     * @return array Recommendations
     */
    private function generateRecommendations(array $userData, float $targetCalories, array $macros): array
    {
        $recommendations = [];

        // General recommendations
        $recommendations['general'] = [
            'Track your food intake daily for best results',
            'Weigh yourself weekly at the same time',
            'Take progress photos monthly',
            'Adjust calories if no progress after 2-3 weeks',
            'Prioritize whole, unprocessed foods',
            'Get 7-9 hours of quality sleep',
            'Manage stress levels'
        ];

        // Goal-specific recommendations
        switch ($userData['goal']) {
            case 'weight_loss':
                $recommendations['goal_specific'] = [
                    'Create a sustainable calorie deficit',
                    'Increase protein to preserve muscle mass',
                    'Include strength training 3x per week',
                    'Eat plenty of vegetables for satiety',
                    'Avoid extreme restrictions to prevent rebound'
                ];
                break;
            case 'muscle_gain':
                $recommendations['goal_specific'] = [
                    'Eat in a slight calorie surplus',
                    'Consume 1g protein per lb body weight',
                    'Time carbs around workouts',
                    'Progressive overload in resistance training',
                    'Get adequate sleep for recovery'
                ];
                break;
            case 'performance':
                $recommendations['goal_specific'] = [
                    'Fuel workouts with adequate carbohydrates',
                    'Time nutrient intake around training',
                    'Prioritize recovery nutrition post-workout',
                    'Stay hydrated during activity',
                    'Consider sports supplements if needed'
                ];
                break;
        }

        // Macro-specific tips
        $recommendations['macro_tips'] = [
            "Aim for {$macros['protein_g']}g protein daily from lean meats, fish, eggs, and legumes",
            "Get {$macros['carbs_g']}g carbs from whole grains, fruits, and vegetables",
            "Include {$macros['fat_g']}g healthy fats from nuts, avocados, and olive oil",
        ];

        return $recommendations;
    }

    /**
     * Validate user data has required fields
     *
     * @param array $userData
     * @throws \InvalidArgumentException
     */
    private function validateUserData(array $userData): void
    {
        $required = ['weight_lbs', 'height_inches', 'age', 'gender', 'activity_level', 'goal'];

        foreach ($required as $field) {
            if (!isset($userData[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!in_array($userData['activity_level'], array_keys(self::ACTIVITY_MULTIPLIERS))) {
            throw new \InvalidArgumentException("Invalid activity level: {$userData['activity_level']}");
        }
    }

    /**
     * Map goal to adjustment key
     */
    private function mapGoalToAdjustmentKey(string $goal): string
    {
        return match($goal) {
            'weight_loss', 'weight_loss_aggressive' => 'weight_loss_moderate',
            'muscle_gain', 'muscle_gain_aggressive' => 'muscle_gain_moderate',
            'maintenance', 'health' => 'maintenance',
            'performance' => 'muscle_gain_mild',
            default => 'maintenance'
        };
    }

    /**
     * Map goal to macro key
     */
    private function mapGoalToMacroKey(string $goal): string
    {
        return match($goal) {
            'weight_loss' => 'weight_loss',
            'muscle_gain' => 'muscle_gain',
            'maintenance' => 'maintenance',
            'performance' => 'performance',
            'health' => 'health',
            default => 'maintenance'
        };
    }

    /**
     * Calculate nutrition profile for a User model
     *
     * @param User $user
     * @param string|null $activityLevel Override activity level
     * @param string|null $goal Override goal
     * @return array
     */
    public function calculateForUser(User $user, ?string $activityLevel = null, ?string $goal = null): array
    {
        $userData = [
            'weight_lbs' => $user->weight ?? 150,
            'height_inches' => $user->height ?? 66,
            'age' => $user->age ?? 30,
            'gender' => $user->gender ?? 'male',
            'activity_level' => $activityLevel ?? $user->activity_level ?? 'moderately_active',
            'goal' => $goal ?? $user->fitness_goal ?? 'maintenance',
            'meals_per_day' => $user->meals_per_day ?? 3
        ];

        return $this->calculateNutritionProfile($userData);
    }
}
