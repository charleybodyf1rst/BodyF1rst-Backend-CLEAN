<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChipperWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chipper_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->integer('total_exercises'); // Number of different exercises
            $table->json('exercises')->nullable(); // Array with exercise and total reps for each
            $table->integer('total_reps_prescribed'); // Total reps in entire chipper
            $table->integer('total_reps_completed')->default(0);
            $table->integer('exercises_completed')->default(0); // How many exercises finished
            $table->integer('current_exercise_reps')->default(0); // Reps done on current exercise
            $table->integer('time_cap_seconds')->nullable(); // Max time allowed
            $table->integer('time_to_complete_seconds')->nullable(); // Actual finish time
            $table->boolean('is_capped')->default(false); // Hit time cap?
            $table->string('time_formatted')->nullable(); // e.g., "23:45"
            $table->json('progress_checkpoints')->nullable(); // Track progress at intervals
            $table->string('partition_strategy')->nullable(); // single, sets, partner
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
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chipper_workouts');
    }
}
