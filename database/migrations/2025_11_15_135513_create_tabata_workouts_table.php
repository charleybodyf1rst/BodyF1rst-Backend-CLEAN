<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTabataWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tabata_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->integer('work_seconds')->default(20); // Standard Tabata: 20s work
            $table->integer('rest_seconds')->default(10); // Standard Tabata: 10s rest
            $table->integer('rounds_per_exercise')->default(8); // Standard Tabata: 8 rounds
            $table->integer('total_tabata_sets')->default(1); // How many Tabata sets
            $table->integer('rest_between_sets_seconds')->nullable(); // Rest between Tabata sets
            $table->json('exercises')->nullable(); // Array of exercises
            $table->integer('total_rounds_completed')->default(0);
            $table->integer('total_reps_completed')->default(0);
            $table->integer('total_duration_seconds')->nullable();
            $table->json('rounds_data')->nullable(); // Track reps per round
            $table->integer('calories_burned')->nullable();
            $table->decimal('average_heart_rate', 5, 2)->nullable();
            $table->decimal('max_heart_rate', 5, 2)->nullable();
            $table->integer('perceived_exertion')->nullable(); // RPE 1-10
            $table->text('notes')->nullable();
            $table->date('workout_date');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_standard_protocol')->default(true); // 20/10/8 protocol
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
        Schema::dropIfExists('tabata_workouts');
    }
}
