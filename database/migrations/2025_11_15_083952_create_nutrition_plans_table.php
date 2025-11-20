<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNutritionPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nutrition_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->integer('duration_days')->default(30);
            $table->integer('daily_calories');
            $table->decimal('daily_protein_g', 8, 2);
            $table->decimal('daily_carbs_g', 8, 2);
            $table->decimal('daily_fat_g', 8, 2);
            $table->integer('bmr')->nullable();
            $table->integer('tdee')->nullable();
            $table->string('goal_type')->nullable(); // weight_loss, maintenance, muscle_gain
            $table->string('activity_level')->nullable();
            $table->json('meals')->nullable(); // Array of daily meals
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for better query performance
            $table->index('coach_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nutrition_plans');
    }
}
