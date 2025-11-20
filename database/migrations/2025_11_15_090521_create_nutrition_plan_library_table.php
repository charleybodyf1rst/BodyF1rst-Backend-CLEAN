<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNutritionPlanLibraryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nutrition_plan_library', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_admin_id'); // Admin who created it
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days')->default(30);
            $table->integer('daily_calories');
            $table->decimal('daily_protein_g', 8, 2);
            $table->decimal('daily_carbs_g', 8, 2);
            $table->decimal('daily_fat_g', 8, 2);
            $table->string('goal_type')->nullable(); // weight_loss, maintenance, muscle_gain
            $table->string('activity_level')->nullable(); // sedentary, lightly_active, etc.
            $table->json('meals')->nullable(); // Array of daily meals
            $table->json('tags')->nullable(); // Array of tags for searching
            $table->string('thumbnail_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('clone_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by_admin_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('created_by_admin_id');
            $table->index('goal_type');
            $table->index('is_featured');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nutrition_plan_library');
    }
}
