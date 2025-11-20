<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plan_workouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('workout_id')->nullable();
            $table->integer('phase')->nullable();
            $table->integer('week')->nullable();
            $table->integer('day')->nullable();
            $table->tinyInteger('is_rest')->default(0)->nullable();
            $table->decimal('sort',8,2)->default(9999)->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('no action');
            $table->foreign('workout_id')->references('id')->on('workouts')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plan_workouts');
    }
}
