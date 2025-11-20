<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHealthMetricsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('health_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');

            // Activity Rings
            $table->integer('active_calories')->default(0);
            $table->integer('move_goal')->default(500);
            $table->integer('exercise_minutes')->default(0);
            $table->integer('exercise_goal')->default(30);
            $table->integer('stand_hours')->default(0);
            $table->integer('stand_goal')->default(12);

            // Vital Signs
            $table->integer('heart_rate')->nullable();
            $table->integer('resting_heart_rate')->nullable();
            $table->integer('hrv')->nullable();
            $table->integer('blood_pressure_systolic')->nullable();
            $table->integer('blood_pressure_diastolic')->nullable();
            $table->integer('blood_oxygen')->nullable();
            $table->integer('respiratory_rate')->nullable();

            // Body Measurements
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('body_fat', 4, 1)->nullable();
            $table->decimal('lean_mass', 5, 2)->nullable();
            $table->decimal('bmi', 3, 1)->nullable();

            // Fitness Metrics
            $table->integer('steps')->default(0);
            $table->decimal('distance', 6, 2)->default(0);
            $table->integer('flights_climbed')->default(0);
            $table->decimal('vo2_max', 4, 1)->nullable();

            // Nutrition
            $table->integer('calories_consumed')->default(0);
            $table->integer('water_intake')->default(0);

            // Sleep
            $table->decimal('sleep_hours', 3, 1)->nullable();

            // Metadata
            $table->enum('last_sync_source', ['HealthKit', 'GoogleFit', 'Manual'])->nullable();
            $table->timestamp('last_sync_timestamp')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['user_id', 'date']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'date']);
            $table->index('last_sync_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('health_metrics');
    }
}
