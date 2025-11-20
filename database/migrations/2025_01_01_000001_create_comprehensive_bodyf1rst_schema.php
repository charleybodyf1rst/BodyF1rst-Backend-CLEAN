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
        // Users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('role', ['user', 'coach', 'admin'])->default('user');
            $table->unsignedBigInteger('coach_id')->nullable();
            $table->json('flags')->nullable(); // Feature flags, preferences, etc.
            $table->json('profile_data')->nullable(); // Height, weight, goals, etc.
            $table->rememberToken();
            $table->timestamps();
            
            $table->index(['role']);
            $table->index(['coach_id']);
            $table->foreign('coach_id')->references('id')->on('users')->onDelete('set null');
        });

        // Recipes table
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('macros'); // calories, protein, carbs, fat, fiber, etc.
            $table->json('ingredients');
            $table->text('instructions')->nullable();
            $table->json('tags')->nullable(); // cuisine, diet, difficulty, etc.
            $table->string('media_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('prep_time')->nullable(); // minutes
            $table->integer('cook_time')->nullable(); // minutes
            $table->integer('servings')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            
            $table->index(['is_public']);
            $table->index(['created_by']);
            $table->fullText(['title', 'description']);
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // Nutrition logs table
        Schema::create('nutrition_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('logged_at');
            $table->decimal('calories', 8, 2);
            $table->json('macros'); // protein, carbs, fat, fiber, sugar, sodium, etc.
            $table->enum('source', ['passio', 'manual', 'recipe', 'barcode'])->default('manual');
            $table->string('food_name');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->default('serving');
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack'])->nullable();
            $table->string('passio_id')->nullable();
            $table->json('metadata')->nullable(); // Additional data from Passio or other sources
            $table->timestamps();
            
            $table->index(['user_id', 'logged_at']);
            $table->index(['user_id', 'meal_type']);
            $table->index(['source']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Passio entities table (cache for Passio API results)
        Schema::create('passio_entities', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique(); // Passio food ID
            $table->string('label');
            $table->json('nutrition'); // Complete nutrition data from Passio
            $table->json('metadata')->nullable(); // Additional Passio data
            $table->string('hash'); // Hash of nutrition data for cache invalidation
            $table->timestamp('last_updated');
            $table->timestamps();
            
            $table->index(['external_id']);
            $table->index(['label']);
            $table->index(['last_updated']);
        });

        // Workouts table
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('blocks'); // Workout structure: exercises, sets, reps, etc.
            $table->json('tags')->nullable(); // muscle_group, difficulty, equipment, etc.
            $table->integer('duration_minutes')->nullable();
            $table->enum('difficulty', ['beginner', 'intermediate', 'advanced'])->nullable();
            $table->json('equipment')->nullable(); // Required equipment
            $table->string('video_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            
            $table->index(['is_public']);
            $table->index(['difficulty']);
            $table->index(['created_by']);
            $table->fullText(['title', 'description']);
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // Workout logs table
        Schema::create('workout_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('logged_at');
            $table->unsignedBigInteger('workout_id')->nullable();
            $table->json('metrics'); // Sets, reps, weight, duration, calories, etc.
            $table->json('exercises_completed'); // Which exercises were completed
            $table->integer('duration_minutes')->nullable();
            $table->decimal('calories_burned', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->enum('completion_status', ['completed', 'partial', 'skipped'])->default('completed');
            $table->timestamps();
            
            $table->index(['user_id', 'logged_at']);
            $table->index(['workout_id']);
            $table->index(['completion_status']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('workout_id')->references('id')->on('workouts')->onDelete('set null');
        });

        // Calendar events table
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->enum('type', ['meal', 'workout', 'cbt', 'appointment', 'reminder'])->default('reminder');
            $table->unsignedBigInteger('ref_id')->nullable(); // Reference to related record
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional event data
            $table->boolean('is_completed')->default(false);
            $table->timestamp('reminder_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'start_time']);
            $table->index(['type', 'ref_id']);
            $table->index(['is_completed']);
            $table->index(['reminder_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // CBT (Cognitive Behavioral Therapy) entries table
        Schema::create('cbt_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('logged_at');
            $table->enum('tool', ['mood_tracker', 'thought_record', 'gratitude', 'meditation', 'breathing', 'journal', 'goal_setting']);
            $table->json('payload'); // Tool-specific data structure
            $table->integer('mood_score')->nullable(); // 1-10 scale
            $table->json('tags')->nullable(); // emotions, triggers, etc.
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'logged_at']);
            $table->index(['tool']);
            $table->index(['mood_score']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Media table
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('url'); // S3 URL or path
            $table->string('filename');
            $table->enum('type', ['image', 'video', 'audio', 'document'])->default('image');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->enum('owner_type', ['user', 'recipe', 'workout', 'system'])->default('user');
            $table->json('tags')->nullable(); // searchable tags
            $table->json('metadata')->nullable(); // dimensions, duration, etc.
            $table->string('s3_bucket')->nullable();
            $table->string('s3_key')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            
            $table->index(['owner_id', 'owner_type']);
            $table->index(['type']);
            $table->index(['is_public']);
            $table->index(['s3_bucket', 's3_key']);
            // Note: No foreign key constraint for polymorphic owner_id
            // Polymorphic integrity should be enforced at the application layer
        });

        // Additional indexes for performance
        Schema::table('nutrition_logs', function (Blueprint $table) {
            $table->index(['user_id', 'logged_at', 'meal_type']);
        });

        Schema::table('workout_logs', function (Blueprint $table) {
            $table->index(['user_id', 'logged_at', 'completion_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('cbt_entries');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('workout_logs');
        Schema::dropIfExists('workouts');
        Schema::dropIfExists('passio_entities');
        Schema::dropIfExists('nutrition_logs');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('users');
    }
};
