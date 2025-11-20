<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePyramidWorkoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pyramid_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workout_log_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('workout_name');
            $table->text('description')->nullable();
            $table->string('pyramid_type'); // ascending, descending, triangle (up then down)
            $table->string('progression_method'); // weight, reps, both
            $table->integer('total_sets');
            $table->integer('sets_completed')->default(0);
            $table->json('exercises')->nullable(); // Array of exercises in pyramid
            $table->json('pyramid_structure')->nullable(); // Rep/weight scheme per set
            $table->integer('starting_reps')->nullable(); // For rep pyramids
            $table->integer('ending_reps')->nullable();
            $table->decimal('starting_weight', 8, 2)->nullable(); // For weight pyramids
            $table->decimal('ending_weight', 8, 2)->nullable();
            $table->string('weight_unit')->default('lbs');
            $table->integer('rep_increment')->nullable(); // How much reps change per set
            $table->decimal('weight_increment', 6, 2)->nullable(); // How much weight changes per set
            $table->integer('rest_seconds_between_sets')->nullable();
            $table->json('sets_data')->nullable(); // Actual performance data
            $table->integer('total_reps_completed')->default(0);
            $table->decimal('total_volume', 10, 2)->default(0);
            $table->integer('calories_burned')->nullable();
            $table->decimal('average_heart_rate', 5, 2)->nullable();
            $table->decimal('max_heart_rate', 5, 2)->nullable();
            $table->integer('perceived_exertion')->nullable(); // RPE 1-10
            $table->text('notes')->nullable();
            $table->date('workout_date');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('workout_date');
            $table->index(['user_id', 'workout_date']);
            $table->index('pyramid_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pyramid_workouts');
    }
}
