<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMealPlanTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meal_plan_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('goal_type')->nullable(); // weight_loss, muscle_gain, maintenance, etc.
            $table->integer('target_calories')->nullable();
            $table->decimal('target_protein_g', 8, 2)->nullable();
            $table->decimal('target_carbs_g', 8, 2)->nullable();
            $table->decimal('target_fat_g', 8, 2)->nullable();
            $table->integer('meals_per_day')->default(3);
            $table->json('meal_schedule')->nullable(); // Times for meals
            $table->json('foods')->nullable(); // List of foods/meals in the plan
            $table->json('restrictions')->nullable(); // Dietary restrictions
            $table->boolean('is_public')->default(false);
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->index('coach_id');
            $table->index('goal_type');
            $table->index(['is_public', 'created_at']);
        });

        // Meal plan assignments table
        Schema::create('meal_plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_template_id')->constrained('meal_plan_templates')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('coach_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // active, completed, cancelled
            $table->decimal('compliance_rate', 5, 2)->nullable(); // Percentage
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->index('user_id');
            $table->index('coach_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meal_plan_assignments');
        Schema::dropIfExists('meal_plan_templates');
    }
}
