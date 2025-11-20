<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsStagToWorkoutExercisesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->tinyInteger('is_stag')->after('superset')->default(0)->nullable();
            $table->integer('stag')->after('is_stag')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropColumn('is_stag');
            $table->dropColumn('stag');
        });
    }
}
