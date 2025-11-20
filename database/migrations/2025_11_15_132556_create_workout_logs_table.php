<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkoutLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workout_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('plan_id')->nullable()->constrained()->onDelete('set null');
            $table->string('workout_name'); // In case workout is deleted
            $table->date('workout_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('duration_minutes')->nullable(); // Total duration
            $table->integer('calories_burned')->nullable();
            $table->decimal('average_heart_rate', 5, 2)->nullable();
            $table->decimal('max_heart_rate', 5, 2)->nullable();
            $table->integer('total_sets')->default(0);
            $table->integer('total_reps')->default(0);
            $table->decimal('total_weight_lifted', 10, 2)->default(0); // Total volume
            $table->string('difficulty_rating')->nullable(); // easy, medium, hard
            $table->integer('perceived_exertion')->nullable(); // 1-10 RPE scale
            $table->boolean('completed')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            $table->index('user_id');
            $table->index('workout_date');
            $table->index(['user_id', 'workout_date']);
            $table->index('completed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workout_logs');
    }
}
