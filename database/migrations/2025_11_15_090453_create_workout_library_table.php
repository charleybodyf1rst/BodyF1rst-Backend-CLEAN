<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkoutLibraryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workout_library', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_admin_id'); // Admin who created it
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // strength, cardio, hiit, yoga, etc.
            $table->string('difficulty_level')->nullable(); // beginner, intermediate, advanced
            $table->string('goal')->nullable(); // strength, hypertrophy, endurance, general_fitness
            $table->integer('duration_weeks')->default(4);
            $table->integer('sessions_per_week')->default(3);
            $table->json('exercises')->nullable(); // Array of exercises with sets/reps/weights
            $table->json('tags')->nullable(); // Array of tags for searching
            $table->string('thumbnail_url')->nullable(); // Image for the workout
            $table->boolean('is_featured')->default(false); // Featured workouts shown prominently
            $table->integer('clone_count')->default(0); // Track how many times it's been cloned
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by_admin_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('created_by_admin_id');
            $table->index('category');
            $table->index('difficulty_level');
            $table->index('goal');
            $table->index('is_featured');
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
        Schema::dropIfExists('workout_library');
    }
}
