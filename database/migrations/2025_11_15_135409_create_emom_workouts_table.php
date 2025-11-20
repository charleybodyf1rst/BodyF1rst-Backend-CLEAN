<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmomWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('emom_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->integer('minute_interval')->default(1); // EMOM 1, EMOM 2, EMOM 3, etc.
            $table->integer('total_minutes'); // Total workout duration
            $table->integer('minutes_completed')->default(0);
            $table->integer('exercises_per_minute')->default(1); // How many exercises each minute
            $table->json('exercises')->nullable(); // Array of exercises with reps per minute
            $table->integer('total_reps_completed')->default(0);
            $table->integer('missed_reps')->default(0); // Reps not completed in time
            $table->integer('rest_seconds_per_minute')->nullable(); // Rest after completing reps
            $table->boolean('alternating')->default(false); // Alternating exercises each minute
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
            $table->index('minute_interval');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('emom_workouts');
    }
}
