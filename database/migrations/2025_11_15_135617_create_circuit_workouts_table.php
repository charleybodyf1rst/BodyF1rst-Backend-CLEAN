<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCircuitWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('circuit_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->integer('total_stations'); // Number of exercises in circuit
            $table->integer('total_circuits')->default(1); // How many times through the circuit
            $table->integer('circuits_completed')->default(0);
            $table->json('stations')->nullable(); // Array of exercises with reps/time
            $table->integer('work_seconds_per_station')->nullable(); // Time at each station
            $table->integer('rest_seconds_between_stations')->nullable();
            $table->integer('rest_seconds_between_circuits')->nullable();
            $table->string('circuit_type')->nullable(); // timed, reps, mixed
            $table->integer('total_reps_completed')->default(0);
            $table->integer('total_duration_seconds')->nullable();
            $table->json('circuit_times')->nullable(); // Time for each circuit
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
            $table->index('circuit_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('circuit_workouts');
    }
}
