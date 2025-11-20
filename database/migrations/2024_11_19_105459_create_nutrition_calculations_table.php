<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateNutritionCalculationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nutrition_calculations', function (Blueprint $table) {
            $table->id();
            $table->string('meta_key')->nullable();
            $table->json('meta_value')->nullable();
            $table->tinyInteger('is_default')->nullable();
            $table->tinyInteger('is_current')->nullable();
            $table->timestamps();
        });

        // Insert default data
        DB::table('nutrition_calculations')->insert([
            [
                'meta_key' => 'bmr',
                'meta_value' => json_encode([
                    'male' => ['weight' => 10, 'height' => 6.25, 'age' => 5, 'additional_value' => 5],
                    'female' => ['weight' => 10, 'height' => 6.25, 'age' => 5, 'additional_value' => 161],
                ]),
                'is_default' => 1,
                'is_current' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'meta_key' => 'tdee',
                'meta_value' => json_encode([
                    'not_active' => 1.2,
                    'slightly_active' => 1.37,
                    'moderately_active' => 1.55,
                    'very_active' => 1.725,
                ]),
                'is_default' => 1,
                'is_current' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'meta_key' => 'macronutrients',
                'meta_value' => json_encode([
                    'tone_tightness' => ['carbs' => 40, 'fat' => 25, 'protein' => 35, 'calories' => -300],
                    'weight_loss' => ['carbs' => 40, 'fat' => 30, 'protein' => 30, 'calories' => -500],
                    'build_muscle' => ['carbs' => 50, 'fat' => 20, 'protein' => 30, 'calories' => -250],
                ]),
                'is_default' => 1,
                'is_current' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nutrition_calculations');
    }
}
