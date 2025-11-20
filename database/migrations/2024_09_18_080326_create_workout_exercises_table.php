<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkoutExercisesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_id')->nullable();
            $table->unsignedBigInteger('exercise_id')->nullable();
            $table->enum('type',['Duration','Sets'])->default('Duration')->nullable();
            $table->integer('min')->default(0)->nullable();
            $table->integer('sec')->default(0)->nullable();
            $table->integer('set')->default(0)->nullable();
            $table->integer('rep')->default(0)->nullable();
            $table->tinyInteger('is_rest')->default(0)->nullable();
            $table->integer('rest_min')->default(0)->nullable();
            $table->integer('rest_sec')->default(0)->nullable();
            $table->decimal('sort',8,2)->default(9999)->nullable();
            $table->timestamps();

            $table->foreign('workout_id')->references('id')->on('workouts')->onDelete('no action');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workout_exercises');
    }
}
