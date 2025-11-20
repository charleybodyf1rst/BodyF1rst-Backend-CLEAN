<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupersetWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('superset_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->string('superset_type')->default('standard'); // standard, compound, isolation, antagonistic
            $table->integer('total_supersets'); // Number of different superset pairs
            $table->json('supersets')->nullable(); // Array of superset pairs
            $table->integer('sets_per_superset')->default(3); // How many sets of each superset
            $table->integer('total_sets_completed')->default(0);
            $table->integer('rest_between_exercises_seconds')->default(0); // Usually 0 for supersets
            $table->integer('rest_between_sets_seconds')->nullable();
            $table->json('sets_data')->nullable(); // Detailed data for each set
            $table->integer('total_reps_completed')->default(0);
            $table->decimal('total_volume', 10, 2)->default(0); // Total weight * reps
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
            $table->index('superset_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('superset_workouts');
    }
}
