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
        Schema::create('meal_plan_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creator_id');
            $table->string('creator_type'); // 'Admin' or 'Coach'
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('goal', ['weight_loss', 'muscle_gain', 'maintenance', 'performance', 'health']);
            $table->string('category', 100)->nullable();
            $table->integer('duration_days'); // 7, 14, 30, etc.
            $table->integer('daily_calories');
            $table->decimal('daily_protein_g', 8, 2);
            $table->decimal('daily_carbs_g', 8, 2);
            $table->decimal('daily_fat_g', 8, 2);
            $table->json('meals_structure'); // E.g., ["breakfast", "snack_1", "lunch", "snack_2", "dinner"]
            $table->json('meal_templates'); // Array of days with meals
            $table->json('tags')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->integer('use_count')->default(0);
            $table->text('instructions')->nullable();
            $table->json('shopping_list')->nullable();
            $table->text('prep_tips')->nullable();
            $table->unsignedBigInteger('cloned_from')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['creator_id', 'creator_type']);
            $table->index('goal');
            $table->index('category');
            $table->index('duration_days');
            $table->index('daily_calories');
            $table->index('is_public');
            $table->index('is_featured');
            $table->index('cloned_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plan_templates');
    }
};
