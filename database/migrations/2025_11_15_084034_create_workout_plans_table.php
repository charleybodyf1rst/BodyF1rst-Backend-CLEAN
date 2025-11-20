<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkoutPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workout_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_weeks')->default(4);
            $table->integer('sessions_per_week')->default(3);
            $table->string('difficulty_level')->nullable(); // beginner, intermediate, advanced
            $table->string('goal')->nullable(); // strength, hypertrophy, endurance, general_fitness
            $table->json('exercises')->nullable(); // Array of exercises with sets/reps/weights
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
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
        Schema::dropIfExists('workout_plans');
    }
}
