<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExerciseSetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exercise_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_log_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->nullable()->constrained()->onDelete('set null');
            $table->string('exercise_name'); // In case exercise is deleted
            $table->integer('set_number'); // 1, 2, 3, etc.
            $table->integer('reps')->nullable();
            $table->decimal('weight', 8, 2)->nullable(); // Weight used (lbs/kg)
            $table->string('weight_unit')->default('lbs'); // lbs or kg
            $table->integer('duration_seconds')->nullable(); // For timed exercises
            $table->decimal('distance', 8, 2)->nullable(); // For distance-based exercises
            $table->string('distance_unit')->nullable(); // miles, km, meters
            $table->integer('rest_seconds')->nullable(); // Rest after this set
            $table->boolean('to_failure')->default(false); // Set taken to failure?
            $table->boolean('warmup_set')->default(false);
            $table->boolean('drop_set')->default(false);
            $table->string('set_type')->nullable(); // normal, superset, circuit, drop
            $table->integer('perceived_exertion')->nullable(); // RPE 1-10
            $table->text('notes')->nullable();
            $table->boolean('completed')->default(true);
            $table->timestamps();

            $table->index('workout_log_id');
            $table->index('exercise_id');
            $table->index('set_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exercise_sets');
    }
}
