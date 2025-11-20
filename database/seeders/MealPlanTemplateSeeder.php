<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MealPlanTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $mealPlans = [
            // 1. Weight Loss Plan - Low Calorie, High Protein
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Sustainable Weight Loss - 7 Day Plan',
                'description' => 'A balanced 1500-calorie meal plan designed for healthy weight loss. High in protein to preserve muscle mass, with plenty of vegetables and whole grains. Perfect for beginners looking to lose 1-2 lbs per week.',
                'goal' => 'weight_loss',
                'category' => 'Weight Loss',
                'duration_days' => 7,
                'daily_calories' => 1500,
                'daily_protein_g' => 120,
                'daily_carbs_g' => 150,
                'daily_fat_g' => 50,
                'meals_structure' => json_encode(['breakfast', 'snack_1', 'lunch', 'snack_2', 'dinner']),
                'meal_templates' => json_encode($this->getWeightLossPlan()),
                'tags' => json_encode(['weight_loss', 'high_protein', 'balanced', 'beginner_friendly']),
                'is_public' => true,
                'is_featured' => true,
                'use_count' => 0,
                'instructions' => 'Drink at least 8 glasses of water daily. Eat meals every 3-4 hours. Feel free to swap similar protein sources. Track your portions carefully for best results.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Chicken breast (2 lbs)', 'Ground turkey (1 lb)', 'Salmon (1 lb)', 'Eggs (12)', 'Greek yogurt (32 oz)'],
                    'Vegetables' => ['Mixed greens (2 bags)', 'Broccoli (1 head)', 'Bell peppers (4)', 'Spinach (1 bag)', 'Tomatoes (6)', 'Cucumbers (3)', 'Carrots (1 bag)'],
                    'Fruits' => ['Apples (7)', 'Bananas (7)', 'Berries (2 cups)', 'Oranges (4)'],
                    'Grains' => ['Brown rice (1 bag)', 'Whole wheat bread (1 loaf)', 'Oatmeal (1 container)', 'Quinoa (1 bag)'],
                    'Healthy Fats' => ['Almonds (8 oz)', 'Avocado (3)', 'Olive oil (1 bottle)'],
                    'Pantry' => ['Black beans (2 cans)', 'Salsa', 'Mustard', 'Honey', 'Cinnamon']
                ]),
                'prep_tips' => 'Meal prep on Sunday: Cook all chicken and turkey. Chop vegetables. Pre-portion snacks. Cook grains in bulk. Store proteins separately from vegetables.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 2. Muscle Gain Plan - High Calorie, High Protein
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Muscle Builder - 7 Day Bulk',
                'description' => 'A 2800-calorie meal plan optimized for muscle growth. High in protein and complex carbs to fuel intense workouts and recovery. Includes 6 meals per day for maximum nutrient absorption.',
                'goal' => 'muscle_gain',
                'category' => 'Muscle Building',
                'duration_days' => 7,
                'daily_calories' => 2800,
                'daily_protein_g' => 210,
                'daily_carbs_g' => 350,
                'daily_fat_g' => 70,
                'meals_structure' => json_encode(['breakfast', 'snack_1', 'lunch', 'snack_2', 'dinner', 'snack_3']),
                'meal_templates' => json_encode($this->getMuscleBuildingPlan()),
                'tags' => json_encode(['muscle_gain', 'high_protein', 'high_calorie', 'bodybuilding']),
                'is_public' => true,
                'is_featured' => true,
                'use_count' => 0,
                'instructions' => 'Consume 1g protein per lb of body weight. Eat within 30 minutes post-workout. Stay hydrated with 1 gallon of water daily. Take meals every 2-3 hours.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Chicken breast (3 lbs)', 'Lean beef (2 lbs)', 'Tilapia (2 lbs)', 'Eggs (24)', 'Whey protein (2 lbs)', 'Cottage cheese (32 oz)'],
                    'Carbs' => ['Sweet potatoes (5 lbs)', 'Brown rice (2 lbs)', 'Whole grain pasta (2 boxes)', 'Oatmeal (2 containers)', 'Whole grain bread (2 loaves)'],
                    'Vegetables' => ['Broccoli (3 heads)', 'Green beans (2 bags)', 'Mixed greens (2 bags)', 'Asparagus (2 bunches)'],
                    'Fruits' => ['Bananas (14)', 'Apples (7)', 'Berries (4 cups)'],
                    'Healthy Fats' => ['Almonds (16 oz)', 'Peanut butter (2 jars)', 'Avocados (7)', 'Olive oil'],
                    'Supplements' => ['Protein powder', 'Creatine', 'BCAAs']
                ]),
                'prep_tips' => 'Cook proteins in bulk on Sunday and Wednesday. Pre-portion rice and sweet potatoes. Pack snacks in containers. Prep protein shakes the night before.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 3. Maintenance Plan - Balanced Nutrition
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Balanced Maintenance - 7 Day Plan',
                'description' => 'A well-rounded 2000-calorie meal plan for maintaining current weight while supporting active lifestyle. Balanced macros with emphasis on whole foods and nutrient density.',
                'goal' => 'maintenance',
                'category' => 'Maintenance',
                'duration_days' => 7,
                'daily_calories' => 2000,
                'daily_protein_g' => 150,
                'daily_carbs_g' => 225,
                'daily_fat_g' => 65,
                'meals_structure' => json_encode(['breakfast', 'snack_1', 'lunch', 'snack_2', 'dinner']),
                'meal_templates' => json_encode($this->getMaintenancePlan()),
                'tags' => json_encode(['maintenance', 'balanced', 'sustainable', 'whole_foods']),
                'is_public' => true,
                'is_featured' => true,
                'use_count' => 0,
                'instructions' => 'Focus on eating slowly and mindfully. Include a variety of colorful vegetables daily. Stay consistent with meal timing. Adjust portions based on activity level.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Chicken (2 lbs)', 'Salmon (1.5 lbs)', 'Turkey (1 lb)', 'Eggs (12)', 'Greek yogurt (24 oz)', 'Tofu (1 package)'],
                    'Grains' => ['Quinoa (1 bag)', 'Brown rice (1 bag)', 'Whole wheat bread (1 loaf)', 'Oats (1 container)', 'Whole grain pasta (1 box)'],
                    'Vegetables' => ['Mixed greens (2 bags)', 'Broccoli (2 heads)', 'Bell peppers (6)', 'Zucchini (3)', 'Tomatoes (6)', 'Mushrooms (8 oz)'],
                    'Fruits' => ['Apples (7)', 'Bananas (7)', 'Berries (2 cups)', 'Grapes (1 bag)'],
                    'Fats' => ['Avocados (4)', 'Almonds (8 oz)', 'Walnuts (4 oz)', 'Olive oil', 'Hummus (16 oz)']
                ]),
                'prep_tips' => 'Prep vegetables on Sunday for easy assembly. Cook grains in bulk. Keep hard-boiled eggs ready. Batch cook proteins twice weekly.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 4. Keto Plan - High Fat, Low Carb
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Keto Transformation - 7 Day Plan',
                'description' => 'A strict ketogenic meal plan with 75% fat, 20% protein, and 5% carbs. Designed to put your body into ketosis for fat burning. Net carbs under 25g daily.',
                'goal' => 'weight_loss',
                'category' => 'Keto',
                'duration_days' => 7,
                'daily_calories' => 1800,
                'daily_protein_g' => 90,
                'daily_carbs_g' => 25,
                'daily_fat_g' => 150,
                'meals_structure' => json_encode(['breakfast', 'lunch', 'dinner', 'snack']),
                'meal_templates' => json_encode($this->getKetoPlan()),
                'tags' => json_encode(['keto', 'low_carb', 'high_fat', 'weight_loss', 'ketosis']),
                'is_public' => true,
                'is_featured' => true,
                'use_count' => 0,
                'instructions' => 'Drink plenty of water and electrolytes. Track net carbs carefully. May experience keto flu in first 3-5 days. Increase salt intake. Test ketones if desired.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Bacon (2 lbs)', 'Ground beef 80/20 (2 lbs)', 'Salmon (1.5 lbs)', 'Eggs (24)', 'Chicken thighs (2 lbs)'],
                    'Fats' => ['Butter (2 sticks)', 'Heavy cream (1 quart)', 'Coconut oil', 'MCT oil', 'Avocados (10)', 'Cheese (various, 2 lbs)'],
                    'Low-Carb Vegetables' => ['Spinach (2 bags)', 'Kale (1 bag)', 'Cauliflower (2 heads)', 'Zucchini (4)', 'Bell peppers (4)', 'Broccoli (1 head)'],
                    'Nuts & Seeds' => ['Macadamia nuts (8 oz)', 'Pecans (8 oz)', 'Chia seeds (8 oz)', 'Flax seeds (8 oz)'],
                    'Other' => ['Almond flour', 'Erythritol', 'Sugar-free sweetener', 'Bone broth']
                ]),
                'prep_tips' => 'Cook bacon in bulk. Make fat bombs for snacks. Prep keto-friendly sauces. Keep MCT oil handy. Pre-portion nuts to avoid overeating.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 5. Vegetarian Plan
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Vegetarian Balance - 7 Day Plan',
                'description' => 'A complete vegetarian meal plan providing all essential amino acids through plant-based proteins. Rich in fiber, vitamins, and minerals while maintaining optimal macro balance.',
                'goal' => 'maintenance',
                'category' => 'Vegetarian',
                'duration_days' => 7,
                'daily_calories' => 1900,
                'daily_protein_g' => 100,
                'daily_carbs_g' => 240,
                'daily_fat_g' => 60,
                'meals_structure' => json_encode(['breakfast', 'snack_1', 'lunch', 'snack_2', 'dinner']),
                'meal_templates' => json_encode($this->getVegetarianPlan()),
                'tags' => json_encode(['vegetarian', 'plant_based', 'high_fiber', 'nutrient_dense']),
                'is_public' => true,
                'is_featured' => false,
                'use_count' => 0,
                'instructions' => 'Combine complementary proteins (beans + rice, hummus + pita). Include B12 supplement. Vary protein sources daily. Soak beans overnight for better digestion.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Tofu (2 packages)', 'Tempeh (2 packages)', 'Eggs (12)', 'Greek yogurt (24 oz)', 'Cottage cheese (16 oz)', 'Chickpeas (3 cans)', 'Black beans (3 cans)', 'Lentils (2 bags)'],
                    'Grains' => ['Quinoa (1 bag)', 'Brown rice (1 bag)', 'Whole wheat pasta (1 box)', 'Oats (1 container)', 'Whole grain bread (1 loaf)'],
                    'Vegetables' => ['Spinach (2 bags)', 'Kale (1 bag)', 'Broccoli (2 heads)', 'Bell peppers (6)', 'Tomatoes (8)', 'Mushrooms (16 oz)', 'Sweet potatoes (5)'],
                    'Fruits' => ['Bananas (7)', 'Apples (7)', 'Berries (3 cups)', 'Oranges (7)'],
                    'Nuts & Seeds' => ['Almonds (12 oz)', 'Walnuts (8 oz)', 'Chia seeds (8 oz)', 'Sunflower seeds (8 oz)', 'Tahini (1 jar)'],
                    'Other' => ['Nutritional yeast', 'Hummus', 'Nut butter', 'Vegetable broth']
                ]),
                'prep_tips' => 'Cook beans and lentils in bulk. Marinate tofu overnight. Pre-chop vegetables. Make hummus from scratch. Batch cook grains.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 6. Vegan Plan
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Complete Vegan - 7 Day Plan',
                'description' => '100% plant-based meal plan with complete proteins, adequate B12, iron, and omega-3s. Diverse whole foods ensure you get all essential nutrients without animal products.',
                'goal' => 'maintenance',
                'category' => 'Vegan',
                'duration_days' => 7,
                'daily_calories' => 1850,
                'daily_protein_g' => 85,
                'daily_carbs_g' => 250,
                'daily_fat_g' => 55,
                'meals_structure' => json_encode(['breakfast', 'snack_1', 'lunch', 'snack_2', 'dinner']),
                'meal_templates' => json_encode($this->getVeganPlan()),
                'tags' => json_encode(['vegan', 'plant_based', 'dairy_free', 'whole_foods']),
                'is_public' => true,
                'is_featured' => false,
                'use_count' => 0,
                'instructions' => 'Supplement with B12 and vitamin D. Include iron-rich foods with vitamin C. Get omega-3s from flax, chia, and walnuts. Ensure adequate protein at each meal.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Tofu (3 packages)', 'Tempeh (2 packages)', 'Edamame (2 bags)', 'Chickpeas (4 cans)', 'Black beans (3 cans)', 'Lentils (2 bags)', 'Hemp hearts (1 bag)'],
                    'Grains' => ['Quinoa (1 bag)', 'Brown rice (1 bag)', 'Oats (1 container)', 'Whole grain bread (1 loaf)', 'Whole wheat pasta (1 box)'],
                    'Vegetables' => ['Kale (2 bags)', 'Spinach (2 bags)', 'Broccoli (2 heads)', 'Brussels sprouts (1 bag)', 'Sweet potatoes (6)', 'Bell peppers (6)', 'Carrots (2 bags)'],
                    'Fruits' => ['Bananas (7)', 'Apples (7)', 'Berries (4 cups)', 'Dates (1 box)', 'Avocados (7)'],
                    'Nuts & Seeds' => ['Almonds (12 oz)', 'Cashews (8 oz)', 'Walnuts (8 oz)', 'Flax seeds (1 bag)', 'Chia seeds (1 bag)', 'Pumpkin seeds (8 oz)'],
                    'Plant Milks' => ['Almond milk (1 quart)', 'Oat milk (1 quart)'],
                    'Other' => ['Nutritional yeast', 'Tahini', 'Almond butter', 'Coconut yogurt', 'Vegetable broth', 'Maple syrup']
                ]),
                'prep_tips' => 'Batch cook legumes. Marinate tofu and tempeh. Make overnight oats. Prep Buddha bowls. Keep trail mix handy. Freeze smoothie packs.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 7. Performance / Athletic Plan
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Athletic Performance - 7 Day Plan',
                'description' => 'High-performance meal plan for athletes and intense training. Optimized carb timing around workouts, adequate protein for recovery, and nutrient-dense whole foods for energy.',
                'goal' => 'performance',
                'category' => 'Athletic Performance',
                'duration_days' => 7,
                'daily_calories' => 3000,
                'daily_protein_g' => 180,
                'daily_carbs_g' => 400,
                'daily_fat_g' => 75,
                'meals_structure' => json_encode(['breakfast', 'pre_workout', 'post_workout', 'lunch', 'snack', 'dinner']),
                'meal_templates' => json_encode($this->getPerformancePlan()),
                'tags' => json_encode(['performance', 'athletic', 'high_carb', 'sports_nutrition']),
                'is_public' => true,
                'is_featured' => true,
                'use_count' => 0,
                'instructions' => 'Time carbs around training. Eat 1-2 hours pre-workout. Consume protein + carbs within 30 minutes post-workout. Stay hydrated with electrolytes. Adjust calories based on training volume.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Chicken breast (3 lbs)', 'Lean beef (2 lbs)', 'Turkey (2 lbs)', 'Salmon (2 lbs)', 'Eggs (24)', 'Greek yogurt (32 oz)', 'Whey protein (2 lbs)'],
                    'Carbs' => ['Sweet potatoes (7 lbs)', 'Brown rice (3 lbs)', 'Oats (2 containers)', 'Quinoa (2 bags)', 'Whole grain bread (2 loaves)', 'Pasta (2 boxes)', 'Bananas (14)'],
                    'Vegetables' => ['Spinach (2 bags)', 'Broccoli (3 heads)', 'Mixed greens (2 bags)', 'Carrots (2 bags)', 'Beets (6)'],
                    'Fruits' => ['Bananas (14)', 'Berries (4 cups)', 'Apples (7)', 'Oranges (7)', 'Dates (1 box)'],
                    'Fats' => ['Avocados (7)', 'Almonds (16 oz)', 'Nut butter (2 jars)', 'Olive oil', 'Salmon (omega-3s)'],
                    'Sports Nutrition' => ['Protein powder', 'BCAAs', 'Electrolyte drinks', 'Energy gels']
                ]),
                'prep_tips' => 'Cook proteins and carbs in large batches. Pre-portion post-workout meals. Pack pre-workout snacks. Make protein shakes ready to blend. Keep energy bars accessible.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 8. Tone & Tighten / Body Recomposition
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Tone & Tighten - 7 Day Plan',
                'description' => 'Moderate calorie plan designed for body recomposition - losing fat while building lean muscle. Higher protein with strategic carb timing for optimal results.',
                'goal' => 'weight_loss',
                'category' => 'Toning',
                'duration_days' => 7,
                'daily_calories' => 1700,
                'daily_protein_g' => 140,
                'daily_carbs_g' => 160,
                'daily_fat_g' => 55,
                'meals_structure' => json_encode(['breakfast', 'snack_1', 'lunch', 'snack_2', 'dinner']),
                'meal_templates' => json_encode($this->getTonePlan()),
                'tags' => json_encode(['toning', 'recomposition', 'lean_muscle', 'fat_loss']),
                'is_public' => true,
                'is_featured' => true,
                'use_count' => 0,
                'instructions' => 'Prioritize protein at every meal. Time carbs around workouts. Include resistance training 3-4x per week. Stay consistent with calorie deficit. Track measurements, not just weight.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Chicken breast (2.5 lbs)', 'Turkey (1.5 lbs)', 'White fish (1.5 lbs)', 'Eggs (18)', 'Greek yogurt (32 oz)', 'Cottage cheese (16 oz)', 'Protein powder (1 lb)'],
                    'Complex Carbs' => ['Sweet potatoes (4)', 'Brown rice (1 bag)', 'Quinoa (1 bag)', 'Oats (1 container)', 'Ezekiel bread (1 loaf)'],
                    'Vegetables' => ['Spinach (2 bags)', 'Broccoli (2 heads)', 'Cauliflower (1 head)', 'Zucchini (4)', 'Bell peppers (6)', 'Asparagus (2 bunches)', 'Green beans (2 bags)'],
                    'Fruits' => ['Berries (3 cups)', 'Apples (7)', 'Grapefruit (4)'],
                    'Healthy Fats' => ['Avocados (5)', 'Almonds (8 oz)', 'Olive oil', 'Chia seeds (8 oz)']
                ]),
                'prep_tips' => 'Grill all proteins on Sunday. Steam vegetables in bulk. Pre-portion meals in containers. Keep protein shakes ready. Prep healthy snacks to avoid temptation.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 9. Mediterranean Plan
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Mediterranean Lifestyle - 7 Day Plan',
                'description' => 'Heart-healthy Mediterranean diet rich in olive oil, fish, whole grains, and vegetables. Anti-inflammatory foods support overall health and longevity. Delicious and sustainable.',
                'goal' => 'health',
                'category' => 'Mediterranean',
                'duration_days' => 7,
                'daily_calories' => 2100,
                'daily_protein_g' => 110,
                'daily_carbs_g' => 230,
                'daily_fat_g' => 85,
                'meals_structure' => json_encode(['breakfast', 'snack_1', 'lunch', 'snack_2', 'dinner']),
                'meal_templates' => json_encode($this->getMediterraneanPlan()),
                'tags' => json_encode(['mediterranean', 'heart_healthy', 'anti_inflammatory', 'longevity']),
                'is_public' => true,
                'is_featured' => false,
                'use_count' => 0,
                'instructions' => 'Use extra virgin olive oil liberally. Eat fish 2-3 times per week. Include legumes daily. Enjoy meals slowly with family. Have fruit for dessert. Moderate wine optional.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Salmon (1.5 lbs)', 'Sardines (4 cans)', 'White fish (1 lb)', 'Chicken (1.5 lbs)', 'Eggs (12)', 'Greek yogurt (24 oz)'],
                    'Legumes' => ['Chickpeas (3 cans)', 'White beans (2 cans)', 'Lentils (2 bags)', 'Fava beans (1 can)'],
                    'Grains' => ['Farro (1 bag)', 'Bulgur (1 bag)', 'Whole wheat pasta (1 box)', 'Pita bread (1 package)', 'Whole grain bread (1 loaf)'],
                    'Vegetables' => ['Tomatoes (12)', 'Cucumbers (6)', 'Bell peppers (6)', 'Eggplant (2)', 'Zucchini (4)', 'Spinach (2 bags)', 'Arugula (2 bags)', 'Red onions (4)'],
                    'Fruits' => ['Oranges (7)', 'Figs (1 container)', 'Grapes (2 bags)', 'Pomegranate (2)', 'Dates (1 box)'],
                    'Fats' => ['Extra virgin olive oil (large)', 'Olives (2 jars)', 'Almonds (12 oz)', 'Walnuts (8 oz)', 'Tahini (1 jar)', 'Feta cheese (8 oz)'],
                    'Herbs' => ['Fresh basil', 'Oregano', 'Parsley', 'Mint', 'Garlic', 'Lemon (8)']
                ]),
                'prep_tips' => 'Make large batch of hummus. Roast vegetables in advance. Prepare tabbouleh. Cook grains in bulk. Marinate proteins with herbs and lemon.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 10. Intermittent Fasting 16:8
            [
                'creator_id' => 1,
                'creator_type' => 'Admin',
                'name' => 'Intermittent Fasting 16:8 - 7 Day Plan',
                'description' => 'Intermittent fasting protocol with 16-hour fast and 8-hour eating window. Two larger meals plus snacks within eating window. Promotes fat loss while preserving muscle.',
                'goal' => 'weight_loss',
                'category' => 'Intermittent Fasting',
                'duration_days' => 7,
                'daily_calories' => 1800,
                'daily_protein_g' => 135,
                'daily_carbs_g' => 180,
                'daily_fat_g' => 60,
                'meals_structure' => json_encode(['meal_1_12pm', 'snack_3pm', 'meal_2_7pm']),
                'meal_templates' => json_encode($this->getIntermittentFastingPlan()),
                'tags' => json_encode(['intermittent_fasting', '16_8', 'time_restricted', 'fat_loss']),
                'is_public' => true,
                'is_featured' => false,
                'use_count' => 0,
                'instructions' => 'Fast from 8pm to 12pm daily. Drink water, black coffee, or tea during fasting. Break fast with protein-rich meal. Last meal by 8pm. May take 3-5 days to adapt.',
                'shopping_list' => json_encode([
                    'Proteins' => ['Chicken (2 lbs)', 'Salmon (1.5 lbs)', 'Turkey (1 lb)', 'Eggs (18)', 'Greek yogurt (16 oz)', 'Cottage cheese (16 oz)'],
                    'Carbs' => ['Sweet potatoes (5)', 'Brown rice (1 bag)', 'Quinoa (1 bag)', 'Oats (1 container)', 'Whole grain bread (1 loaf)'],
                    'Vegetables' => ['Spinach (2 bags)', 'Broccoli (2 heads)', 'Cauliflower (1 head)', 'Bell peppers (6)', 'Zucchini (4)', 'Asparagus (2 bunches)'],
                    'Fruits' => ['Berries (2 cups)', 'Apples (7)', 'Bananas (7)'],
                    'Fats' => ['Avocados (7)', 'Almonds (12 oz)', 'Olive oil', 'Nut butter'],
                    'Beverages' => ['Black coffee', 'Green tea', 'Herbal tea', 'Sparkling water']
                ]),
                'prep_tips' => 'Prep both meals for the day. Keep snacks portion-controlled. Plan eating window around social events. Have filling foods ready to break fast. Stay hydrated during fasting.',
                'cloned_from' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('meal_plan_templates')->insert($mealPlans);
    }

    /**
     * Generate sample meal data for each plan
     * In production, these would be populated from Passio API or meal database
     */
    private function getWeightLossPlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Oatmeal with Berries', 'calories' => 300, 'protein' => 12, 'carbs' => 45, 'fat' => 8],
                ['type' => 'snack_1', 'name' => 'Apple with Almond Butter', 'calories' => 180, 'protein' => 5, 'carbs' => 20, 'fat' => 8],
                ['type' => 'lunch', 'name' => 'Grilled Chicken Salad', 'calories' => 400, 'protein' => 35, 'carbs' => 30, 'fat' => 12],
                ['type' => 'snack_2', 'name' => 'Greek Yogurt with Honey', 'calories' => 150, 'protein' => 18, 'carbs' => 15, 'fat' => 3],
                ['type' => 'dinner', 'name' => 'Baked Salmon with Broccoli & Quinoa', 'calories' => 470, 'protein' => 50, 'carbs' => 40, 'fat' => 19]
            ]],
            ['day' => 2, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Egg White Scramble with Veggies', 'calories' => 280, 'protein' => 22, 'carbs' => 20, 'fat' => 10],
                ['type' => 'snack_1', 'name' => 'Carrots & Hummus', 'calories' => 150, 'protein' => 5, 'carbs' => 18, 'fat' => 7],
                ['type' => 'lunch', 'name' => 'Turkey & Avocado Wrap', 'calories' => 420, 'protein' => 30, 'carbs' => 35, 'fat' => 16],
                ['type' => 'snack_2', 'name' => 'Protein Shake', 'calories' => 160, 'protein' => 24, 'carbs' => 12, 'fat' => 3],
                ['type' => 'dinner', 'name' => 'Chicken Stir-Fry with Brown Rice', 'calories' => 490, 'protein' => 42, 'carbs' => 52, 'fat' => 12]
            ]],
            // Days 3-7 would follow similar pattern with variety
        ];
    }

    private function getMuscleBuildingPlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Egg & Oat Pancakes with Banana', 'calories' => 550, 'protein' => 35, 'carbs' => 65, 'fat' => 14],
                ['type' => 'snack_1', 'name' => 'Protein Shake with Oats', 'calories' => 350, 'protein' => 30, 'carbs' => 45, 'fat' => 8],
                ['type' => 'lunch', 'name' => 'Beef & Sweet Potato Bowl', 'calories' => 680, 'protein' => 48, 'carbs' => 75, 'fat' => 18],
                ['type' => 'snack_2', 'name' => 'Peanut Butter Sandwich', 'calories' => 400, 'protein' => 16, 'carbs' => 42, 'fat' => 18],
                ['type' => 'dinner', 'name' => 'Chicken Breast with Pasta & Veggies', 'calories' => 650, 'protein' => 55, 'carbs' => 72, 'fat' => 12],
                ['type' => 'snack_3', 'name' => 'Cottage Cheese with Almonds', 'calories' => 270, 'protein' => 28, 'carbs' => 12, 'fat' => 14]
            ]],
        ];
    }

    private function getMaintenancePlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Greek Yogurt Parfait', 'calories' => 350, 'protein' => 20, 'carbs' => 45, 'fat' => 10],
                ['type' => 'snack_1', 'name' => 'Mixed Nuts & Dried Fruit', 'calories' => 200, 'protein' => 6, 'carbs' => 22, 'fat' => 12],
                ['type' => 'lunch', 'name' => 'Quinoa Buddha Bowl', 'calories' => 500, 'protein' => 25, 'carbs' => 60, 'fat' => 18],
                ['type' => 'snack_2', 'name' => 'Hummus with Veggies', 'calories' => 180, 'protein' => 7, 'carbs' => 20, 'fat' => 9],
                ['type' => 'dinner', 'name' => 'Grilled Chicken with Roasted Vegetables', 'calories' => 520, 'protein' => 45, 'carbs' => 40, 'fat' => 18]
            ]],
        ];
    }

    private function getKetoPlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Bacon & Eggs with Avocado', 'calories' => 520, 'protein' => 28, 'carbs' => 6, 'fat' => 42],
                ['type' => 'lunch', 'name' => 'Cobb Salad with Ranch', 'calories' => 580, 'protein' => 32, 'carbs' => 8, 'fat' => 46],
                ['type' => 'dinner', 'name' => 'Ribeye Steak with Butter Broccoli', 'calories' => 650, 'protein' => 45, 'carbs' => 10, 'fat' => 52],
                ['type' => 'snack', 'name' => 'Cheese & Macadamia Nuts', 'calories' => 250, 'protein' => 10, 'carbs' => 4, 'fat' => 24]
            ]],
        ];
    }

    private function getVegetarianPlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Veggie Omelet with Toast', 'calories' => 380, 'protein' => 22, 'carbs' => 38, 'fat' => 14],
                ['type' => 'snack_1', 'name' => 'Apple with Almond Butter', 'calories' => 180, 'protein' => 5, 'carbs' => 22, 'fat' => 9],
                ['type' => 'lunch', 'name' => 'Chickpea Buddha Bowl', 'calories' => 480, 'protein' => 18, 'carbs' => 65, 'fat' => 16],
                ['type' => 'snack_2', 'name' => 'Greek Yogurt with Berries', 'calories' => 180, 'protein' => 15, 'carbs' => 22, 'fat' => 4],
                ['type' => 'dinner', 'name' => 'Tofu Stir-Fry with Brown Rice', 'calories' => 520, 'protein' => 28, 'carbs' => 68, 'fat' => 16]
            ]],
        ];
    }

    private function getVeganPlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Oatmeal with Chia & Berries', 'calories' => 350, 'protein' => 12, 'carbs' => 58, 'fat' => 10],
                ['type' => 'snack_1', 'name' => 'Hummus with Veggie Sticks', 'calories' => 180, 'protein' => 7, 'carbs' => 20, 'fat' => 9],
                ['type' => 'lunch', 'name' => 'Lentil & Quinoa Bowl', 'calories' => 460, 'protein' => 20, 'carbs' => 70, 'fat' => 12],
                ['type' => 'snack_2', 'name' => 'Trail Mix with Seeds', 'calories' => 200, 'protein' => 8, 'carbs' => 18, 'fat' => 12],
                ['type' => 'dinner', 'name' => 'Tempeh Tacos with Avocado', 'calories' => 520, 'protein' => 28, 'carbs' => 58, 'fat' => 22]
            ]],
        ];
    }

    private function getPerformancePlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Protein Pancakes with Fruit', 'calories' => 520, 'protein' => 35, 'carbs' => 68, 'fat' => 12],
                ['type' => 'pre_workout', 'name' => 'Banana with Almond Butter', 'calories' => 250, 'protein' => 6, 'carbs' => 35, 'fat' => 10],
                ['type' => 'post_workout', 'name' => 'Protein Shake with Oats', 'calories' => 400, 'protein' => 40, 'carbs' => 50, 'fat' => 5],
                ['type' => 'lunch', 'name' => 'Beef & Sweet Potato Power Bowl', 'calories' => 750, 'protein' => 52, 'carbs' => 85, 'fat' => 18],
                ['type' => 'snack', 'name' => 'Greek Yogurt with Granola', 'calories' => 320, 'protein' => 20, 'carbs' => 42, 'fat' => 8],
                ['type' => 'dinner', 'name' => 'Chicken Pasta with Vegetables', 'calories' => 760, 'protein' => 58, 'carbs' => 92, 'fat' => 18]
            ]],
        ];
    }

    private function getTonePlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Egg White Frittata with Veggies', 'calories' => 300, 'protein' => 28, 'carbs' => 22, 'fat' => 10],
                ['type' => 'snack_1', 'name' => 'Protein Shake', 'calories' => 180, 'protein' => 25, 'carbs' => 15, 'fat' => 3],
                ['type' => 'lunch', 'name' => 'Grilled Chicken Caesar Salad', 'calories' => 420, 'protein' => 38, 'carbs' => 28, 'fat' => 16],
                ['type' => 'snack_2', 'name' => 'Cottage Cheese with Berries', 'calories' => 160, 'protein' => 18, 'carbs' => 16, 'fat' => 4],
                ['type' => 'dinner', 'name' => 'Baked Fish with Asparagus & Quinoa', 'calories' => 450, 'protein' => 42, 'carbs' => 45, 'fat' => 12]
            ]],
        ];
    }

    private function getMediterraneanPlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'breakfast', 'name' => 'Greek Yogurt with Honey & Walnuts', 'calories' => 380, 'protein' => 18, 'carbs' => 42, 'fat' => 16],
                ['type' => 'snack_1', 'name' => 'Hummus with Whole Wheat Pita', 'calories' => 240, 'protein' => 8, 'carbs' => 28, 'fat' => 12],
                ['type' => 'lunch', 'name' => 'Mediterranean Chickpea Salad', 'calories' => 480, 'protein' => 16, 'carbs' => 55, 'fat' => 22],
                ['type' => 'snack_2', 'name' => 'Olives & Feta with Tomatoes', 'calories' => 180, 'protein' => 6, 'carbs' => 10, 'fat' => 14],
                ['type' => 'dinner', 'name' => 'Grilled Salmon with Tabbouleh', 'calories' => 580, 'protein' => 42, 'carbs' => 48, 'fat' => 24]
            ]],
        ];
    }

    private function getIntermittentFastingPlan()
    {
        return [
            ['day' => 1, 'meals' => [
                ['type' => 'meal_1_12pm', 'name' => 'Chicken & Quinoa Power Bowl', 'calories' => 650, 'protein' => 52, 'carbs' => 68, 'fat' => 20],
                ['type' => 'snack_3pm', 'name' => 'Greek Yogurt with Almonds', 'calories' => 280, 'protein' => 20, 'carbs' => 24, 'fat' => 12],
                ['type' => 'meal_2_7pm', 'name' => 'Salmon with Sweet Potato & Broccoli', 'calories' => 620, 'protein' => 48, 'carbs' => 58, 'fat' => 22]
            ]],
        ];
    }
}
