<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVisibilityToExercisesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->enum('visibility_type',['Public','Private'])->after('is_active')->default('Public')->nullable();
        });
        Schema::table('workouts', function (Blueprint $table) {
            $table->enum('visibility_type',['Public','Private'])->after('is_active')->default('Public')->nullable();
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->enum('visibility_type',['Public','Private'])->after('is_active')->default('Public')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('exercises', function (Blueprint $table) {
            //
        });
    }
}
