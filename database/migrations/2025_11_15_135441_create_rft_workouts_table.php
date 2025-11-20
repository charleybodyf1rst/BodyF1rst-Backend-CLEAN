<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRftWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rft_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->integer('prescribed_rounds'); // Number of rounds to complete
            $table->integer('rounds_completed')->default(0);
            $table->integer('reps_in_partial_round')->default(0); // If didn't finish last round
            $table->json('exercises')->nullable(); // Array of exercises with reps per round
            $table->integer('exercises_per_round')->default(1);
            $table->integer('total_reps')->nullable(); // Total reps in workout
            $table->integer('time_to_complete_seconds')->nullable(); // Finish time
            $table->integer('time_cap_seconds')->nullable(); // Max time allowed
            $table->boolean('is_capped')->default(false); // Hit time cap?
            $table->string('time_formatted')->nullable(); // e.g., "12:34"
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
            $table->index('prescribed_rounds');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rft_workouts');
    }
}
