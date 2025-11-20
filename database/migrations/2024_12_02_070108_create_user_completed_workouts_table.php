<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserCompletedWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_completed_workouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('plan_workout_id')->nullable();
            $table->unsignedBigInteger('workout_id')->nullable();
            $table->unsignedBigInteger('workout_exercise_id')->nullable();
            $table->unsignedBigInteger('exercise_id')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->enum('status',['Completed','In Progress'])->nullable();
            $table->timestamps();


            $table->foreign("user_id")->references("id")->on("users")->onDelete("no action");
            $table->foreign("plan_id")->references("id")->on("plans")->onDelete("no action");
            $table->foreign("plan_workout_id")->references("id")->on("plan_workouts")->onDelete("no action");
            $table->foreign("workout_id")->references("id")->on("workouts")->onDelete("no action");
            $table->foreign("workout_exercise_id")->references("id")->on("workout_exercises")->onDelete("no action");
            $table->foreign("exercise_id")->references("id")->on("exercises")->onDelete("no action");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_completed_workouts');
    }
}
