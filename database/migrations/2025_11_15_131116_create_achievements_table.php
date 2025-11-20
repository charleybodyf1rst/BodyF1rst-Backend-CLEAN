<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAchievementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'workout_streak_7'
            $table->string('name'); // e.g., '7-Day Workout Streak'
            $table->text('description')->nullable(); // Description of achievement
            $table->string('category')->default('general'); // workout, nutrition, overall, etc.
            $table->integer('points_reward')->default(0); // Body points to award
            $table->string('icon')->nullable(); // Icon/badge image
            $table->integer('requirement_value')->nullable(); // e.g., 7 for 7-day streak
            $table->string('requirement_type')->nullable(); // streak, count, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('achievements');
    }
}
