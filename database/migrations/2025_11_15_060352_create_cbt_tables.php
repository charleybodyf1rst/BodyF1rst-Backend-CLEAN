<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCbtTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // CBT Programs/Plans
        Schema::create('cbt_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('focus_area')->nullable(); // anxiety, depression, stress, etc.
            $table->integer('duration_weeks')->default(8);
            $table->json('modules')->nullable(); // List of CBT modules/topics
            $table->boolean('is_public')->default(false);
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->index('coach_id');
            $table->index('focus_area');
            $table->index(['is_public', 'created_at']);
        });

        // CBT Sessions (individual therapy sessions or program sessions)
        Schema::create('cbt_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('coach_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('cbt_plan_id')->nullable()->constrained('cbt_plans')->onDelete('set null');
            $table->string('session_type')->nullable(); // individual, group, self-guided
            $table->string('topic')->nullable();
            $table->text('notes')->nullable();
            $table->text('coach_notes')->nullable();
            $table->integer('mood_before')->nullable(); // 1-10 scale
            $table->integer('mood_after')->nullable(); // 1-10 scale
            $table->integer('anxiety_level')->nullable(); // 1-10 scale
            $table->json('cognitive_distortions_identified')->nullable();
            $table->json('coping_strategies_used')->nullable();
            $table->timestamp('session_date')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled
            $table->timestamps();

            $table->index('user_id');
            $table->index('coach_id');
            $table->index('cbt_plan_id');
            $table->index('status');
            $table->index('session_date');
        });

        // CBT Exercises/Homework
        Schema::create('cbt_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cbt_session_id')->nullable()->constrained('cbt_sessions')->onDelete('cascade');
            $table->foreignId('cbt_plan_id')->nullable()->constrained('cbt_plans')->onDelete('set null');
            $table->string('exercise_type'); // thought_record, behavioral_activation, exposure, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('prompts')->nullable(); // Questions or prompts for the exercise
            $table->json('responses')->nullable(); // User's responses
            $table->date('assigned_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('assigned'); // assigned, in_progress, completed, skipped
            $table->text('reflection')->nullable(); // User's reflection on the exercise
            $table->timestamps();

            $table->index('user_id');
            $table->index('cbt_session_id');
            $table->index('status');
            $table->index(['assigned_date', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cbt_exercises');
        Schema::dropIfExists('cbt_sessions');
        Schema::dropIfExists('cbt_plans');
    }
}
