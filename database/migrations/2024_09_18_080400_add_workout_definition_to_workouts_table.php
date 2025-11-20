<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->text('workout_definition')->nullable()->after('description');
            $table->string('target_areas')->nullable()->after('workout_definition');
            $table->integer('estimated_duration')->nullable()->after('target_areas');
            $table->enum('intensity_level', ['low', 'medium', 'high'])->default('medium')->after('estimated_duration');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn(['workout_definition', 'target_areas', 'estimated_duration', 'intensity_level']);
        });
    }
};
