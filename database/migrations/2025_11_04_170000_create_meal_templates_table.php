<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meal_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack']);
            
            // JSON field for storing food items with portions
            $table->json('foods'); // [{food_name, serving_size, serving_unit, quantity, calories, protein, carbs, fat}]
            
            // Calculated totals
            $table->integer('total_calories')->default(0);
            $table->decimal('total_protein_g', 8, 2)->default(0);
            $table->decimal('total_carbs_g', 8, 2)->default(0);
            $table->decimal('total_fat_g', 8, 2)->default(0);
            $table->decimal('total_fiber_g', 8, 2)->nullable();
            
            // Template metadata
            $table->boolean('is_public')->default(false); // Can be shared with other coaches
            $table->integer('use_count')->default(0); // Track how many times used
            $table->string('category')->nullable(); // e.g., "High Protein", "Vegetarian", "Pre-Workout"
            $table->json('tags')->nullable(); // ["quick", "easy", "budget-friendly"]
            
            // Preparation details
            $table->integer('prep_time_minutes')->nullable();
            $table->integer('cook_time_minutes')->nullable();
            $table->text('instructions')->nullable();
            $table->string('image_url')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('coach_id');
            $table->index('meal_type');
            $table->index('is_public');
        });

        Schema::create('meal_template_plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrition_plan_id')->constrained('nutrition_plans')->onDelete('cascade');
            $table->foreignId('meal_template_id')->constrained('meal_templates')->onDelete('cascade');
            $table->integer('day_number'); // Which day in the plan (1-N)
            $table->enum('meal_slot', ['breakfast', 'lunch', 'dinner', 'snack']); // Which meal slot
            $table->timestamps();
            
            // Ensure no duplicate assignments
            $table->unique(['nutrition_plan_id', 'day_number', 'meal_slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_template_plan_assignments');
        Schema::dropIfExists('meal_templates');
    }
};
