<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarStreaksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_streaks', function (Blueprint $table) {
            $table->id();

            // User relationship
            $table->unsignedBigInteger('user_id');

            // Streak type
            $table->enum('streak_type', [
                'workout',
                'nutrition',
                'checkin',
                'water',
                'sleep',
                'meditation',
                'general_activity'
            ]);

            // Current streak
            $table->integer('current_streak')->default(0);
            $table->date('current_streak_start')->nullable();
            $table->date('last_activity_date')->nullable();

            // Best streak
            $table->integer('longest_streak')->default(0);
            $table->date('longest_streak_start')->nullable();
            $table->date('longest_streak_end')->nullable();

            // Statistics
            $table->integer('total_activities')->default(0);
            $table->json('activity_dates')->nullable(); // Last 365 days for heat map

            // Milestones achieved
            $table->json('milestones')->nullable(); // [7, 14, 30, 60, 90, 180, 365]

            // Freeze/protection (miss a day but keep streak)
            $table->integer('streak_freezes_available')->default(0);
            $table->integer('streak_freezes_used')->default(0);

            $table->timestamps();

            // Foreign Keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index('streak_type');
            $table->index(['user_id', 'streak_type']);
            $table->index('last_activity_date');

            // Unique constraint
            $table->unique(['user_id', 'streak_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_streaks');
    }
}
