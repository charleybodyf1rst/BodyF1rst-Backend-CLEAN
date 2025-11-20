<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAmrapWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('amrap_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->string('amrap_type')->default('rounds'); // rounds or reps
            $table->integer('time_cap_minutes'); // Time limit for AMRAP
            $table->integer('rounds_completed')->default(0); // For rounds-based AMRAP
            $table->integer('total_reps_completed')->default(0); // For reps-based AMRAP
            $table->integer('prescribed_reps_per_round')->nullable(); // Reps per exercise in each round
            $table->integer('partial_round_reps')->default(0); // Reps completed in partial round
            $table->json('exercises')->nullable(); // Array of exercises with reps
            $table->integer('total_exercises_per_round')->default(1);
            $table->decimal('score', 8, 2)->nullable(); // Final score (rounds + partial)
            $table->integer('calories_burned')->nullable();
            $table->decimal('average_heart_rate', 5, 2)->nullable();
            $table->decimal('max_heart_rate', 5, 2)->nullable();
            $table->integer('perceived_exertion')->nullable(); // RPE 1-10
            $table->text('notes')->nullable();
            $table->date('workout_date');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_rx')->default(false); // Completed as prescribed (RX)?
            $table->timestamps();

            $table->index('user_id');
            $table->index('workout_date');
            $table->index(['user_id', 'workout_date']);
            $table->index('amrap_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('amrap_workouts');
    }
}
