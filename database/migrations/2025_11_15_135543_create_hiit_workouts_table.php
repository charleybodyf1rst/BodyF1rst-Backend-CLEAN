<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHiitWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hiit_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->integer('work_seconds'); // High-intensity interval duration
            $table->integer('rest_seconds'); // Rest interval duration
            $table->integer('total_rounds'); // Number of intervals
            $table->integer('rounds_completed')->default(0);
            $table->integer('warmup_seconds')->nullable();
            $table->integer('cooldown_seconds')->nullable();
            $table->json('intervals')->nullable(); // Array of interval details
            $table->string('hiit_type')->nullable(); // sprint, bike, row, bodyweight, etc.
            $table->decimal('work_to_rest_ratio', 5, 2)->nullable(); // e.g., 2:1, 1:1, 1:2
            $table->integer('total_work_seconds')->nullable();
            $table->integer('total_rest_seconds')->nullable();
            $table->integer('total_duration_seconds')->nullable();
            $table->integer('calories_burned')->nullable();
            $table->decimal('average_heart_rate', 5, 2)->nullable();
            $table->decimal('max_heart_rate', 5, 2)->nullable();
            $table->decimal('average_power_watts', 8, 2)->nullable(); // For bike/row
            $table->decimal('distance_total', 8, 2)->nullable(); // For running/biking
            $table->string('distance_unit')->nullable();
            $table->integer('perceived_exertion')->nullable(); // RPE 1-10
            $table->text('notes')->nullable();
            $table->date('workout_date');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('workout_date');
            $table->index(['user_id', 'workout_date']);
            $table->index('hiit_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hiit_workouts');
    }
}
