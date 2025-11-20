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
        Schema::create('friend_activity_feed', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // The user who performed the activity
            $table->enum('activity_type', [
                'completed_workout',
                'completed_meal',
                'earned_badge',
                'reached_milestone',
                'uploaded_progression_photo',
                'completed_challenge',
                'shared_workout',
                'shared_nutrition',
                'new_personal_record'
            ]);
            $table->text('activity_description');
            $table->json('activity_data')->nullable(); // Additional data about the activity
            $table->string('activity_icon')->nullable();
            $table->string('activity_image_url')->nullable();
            $table->boolean('is_public')->default(true); // Can friends see this?
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Index for efficient feed queries
            $table->index(['created_at', 'is_public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friend_activity_feed');
    }
};
