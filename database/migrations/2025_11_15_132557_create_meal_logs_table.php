<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMealLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('meal_type'); // breakfast, lunch, dinner, snack
            $table->timestamp('meal_time');
            $table->string('meal_name')->nullable();
            $table->decimal('total_calories', 8, 2)->default(0);
            $table->decimal('protein_grams', 6, 2)->default(0);
            $table->decimal('carbs_grams', 6, 2)->default(0);
            $table->decimal('fat_grams', 6, 2)->default(0);
            $table->decimal('fiber_grams', 6, 2)->default(0);
            $table->decimal('sugar_grams', 6, 2)->default(0);
            $table->decimal('sodium_mg', 8, 2)->default(0);
            $table->decimal('water_ml', 8, 2)->default(0);
            $table->string('photo_url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_on_plan')->default(false); // Following meal plan?
            $table->timestamps();

            $table->index('user_id');
            $table->index('meal_time');
            $table->index('meal_type');
            $table->index(['user_id', 'meal_time']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meal_logs');
    }
}
