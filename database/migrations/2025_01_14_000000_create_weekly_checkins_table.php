<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWeeklyCheckinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('weekly_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('coach_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('checkin_date');
            $table->integer('week_number'); // Week number in the program

            // Physical Measurements
            $table->decimal('current_weight', 8, 2)->nullable();
            $table->string('weight_unit', 10)->default('lbs'); // lbs or kg
            $table->decimal('body_fat_percentage', 5, 2)->nullable();
            $table->json('measurements')->nullable(); // chest, waist, hips, arms, legs, etc.

            // Progress Photos
            $table->string('front_photo')->nullable();
            $table->string('side_photo')->nullable();
            $table->string('back_photo')->nullable();

            // Wellness Indicators
            $table->integer('energy_level')->nullable(); // 1-10 scale
            $table->integer('mood')->nullable(); // 1-10 scale
            $table->integer('sleep_quality')->nullable(); // 1-10 scale
            $table->decimal('sleep_hours', 4, 2)->nullable();
            $table->integer('stress_level')->nullable(); // 1-10 scale

            // Compliance & Activity
            $table->integer('workouts_completed')->default(0);
            $table->integer('workouts_planned')->default(0);
            $table->integer('meals_logged')->default(0);
            $table->decimal('water_intake_oz', 6, 2)->nullable();

            // Subjective Feedback
            $table->text('what_went_well')->nullable();
            $table->text('challenges_faced')->nullable();
            $table->text('goals_next_week')->nullable();
            $table->text('questions_for_coach')->nullable();
            $table->text('additional_notes')->nullable();

            // Coach Response
            $table->text('coach_feedback')->nullable();
            $table->timestamp('coach_reviewed_at')->nullable();
            $table->json('coach_recommendations')->nullable(); // Adjustments to plan, tips, etc.

            // Status
            $table->enum('status', ['pending', 'submitted', 'reviewed', 'archived'])->default('pending');
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('coach_id');
            $table->index('checkin_date');
            $table->index('status');
            $table->index(['user_id', 'checkin_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('weekly_checkins');
    }
}
