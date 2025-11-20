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
        Schema::create('meal_plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meal_plan_template_id');
            $table->unsignedBigInteger('user_id')->nullable(); // Assigned to specific user
            $table->unsignedBigInteger('organization_id')->nullable(); // OR assigned to entire organization
            $table->unsignedBigInteger('assigned_by'); // Admin or Coach who assigned
            $table->string('assigner_type'); // 'Admin' or 'Coach'
            $table->date('start_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('meal_plan_template_id')->references('id')->on('meal_plan_templates')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            // Indexes
            $table->index('meal_plan_template_id');
            $table->index('user_id');
            $table->index('organization_id');
            $table->index(['assigned_by', 'assigner_type']);
            $table->index('start_date');

            // Unique constraints to prevent duplicate assignments
            $table->unique(['meal_plan_template_id', 'user_id'], 'unique_user_assignment');
            $table->unique(['meal_plan_template_id', 'organization_id'], 'unique_org_assignment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plan_assignments');
    }
};
