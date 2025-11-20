<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDropSetWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('drop_set_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->foreignId('exercise_id')->nullable()->constrained()->onDelete('set null');
            $table->string('exercise_name'); // In case exercise is deleted
            $table->integer('total_drop_sets'); // Number of drop set sequences
            $table->json('exercises')->nullable(); // If multiple exercises
            $table->integer('drops_per_set')->default(3); // How many drops per sequence
            $table->decimal('starting_weight', 8, 2); // Initial weight
            $table->string('weight_unit')->default('lbs');
            $table->decimal('drop_percentage', 5, 2)->default(20.00); // % to reduce weight each drop
            $table->integer('rest_between_drops_seconds')->default(0); // Usually 0-10s
            $table->integer('rest_between_sets_seconds')->nullable();
            $table->json('sets_data')->nullable(); // Detailed data for each drop set
            $table->integer('total_reps_completed')->default(0);
            $table->decimal('total_volume', 10, 2)->default(0); // Total weight * reps
            $table->boolean('to_failure')->default(true); // Each drop to failure?
            $table->integer('total_duration_seconds')->nullable();
            $table->integer('calories_burned')->nullable();
            $table->decimal('average_heart_rate', 5, 2)->nullable();
            $table->decimal('max_heart_rate', 5, 2)->nullable();
            $table->integer('perceived_exertion')->nullable(); // RPE 1-10
            $table->text('notes')->nullable();
            $table->date('workout_date');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('workout_date');
            $table->index(['user_id', 'workout_date']);
            $table->index('exercise_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('drop_set_workouts');
    }
}
