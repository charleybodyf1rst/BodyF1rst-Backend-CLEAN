<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();

            // Ownership
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('coach_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();

            // Event Type & Details
            $table->enum('event_type', [
                'appointment',
                'workout',
                'meal',
                'checkin',
                'cbt_session',
                'assessment',
                'blocked_time',
                'personal',
                'reminder',
                'recurring_instance'
            ]);

            // Event Information
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();

            // Timing
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->integer('duration')->comment('Duration in minutes');
            $table->boolean('all_day')->default(false);
            $table->string('timezone')->default('UTC');

            // Visual
            $table->string('color')->default('#3B82F6'); // Tailwind blue-500
            $table->string('icon')->nullable();

            // Status
            $table->enum('status', [
                'scheduled',
                'in_progress',
                'completed',
                'cancelled',
                'no_show',
                'rescheduled'
            ])->default('scheduled');

            // Related Entities
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('workout_id')->nullable();
            $table->unsignedBigInteger('checkin_id')->nullable();
            $table->unsignedBigInteger('meal_plan_id')->nullable();
            $table->unsignedBigInteger('assessment_id')->nullable();

            // Recurring
            $table->unsignedBigInteger('recurring_pattern_id')->nullable();
            $table->unsignedBigInteger('parent_event_id')->nullable();
            $table->date('recurrence_date')->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Custom data per event type
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Reminders
            $table->boolean('reminder_enabled')->default(true);
            $table->json('reminder_times')->nullable(); // [1440, 60, 15] minutes before
            $table->timestamp('last_reminder_sent_at')->nullable();

            // Tracking
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->integer('body_points_awarded')->default(0);

            // External Calendar
            $table->string('external_calendar_id')->nullable();
            $table->string('external_event_id')->nullable();
            $table->string('ical_uid')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('cascade');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            $table->foreign('checkin_id')->references('id')->on('weekly_checkins')->onDelete('cascade');
            $table->foreign('recurring_pattern_id')->references('id')->on('calendar_recurring_patterns')->onDelete('set null');
            $table->foreign('parent_event_id')->references('id')->on('calendar_events')->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index('coach_id');
            $table->index('organization_id');
            $table->index('event_type');
            $table->index('start_time');
            $table->index('end_time');
            $table->index('status');
            $table->index(['user_id', 'start_time']);
            $table->index(['coach_id', 'start_time']);
            $table->index(['event_type', 'start_time']);
            $table->index('recurring_pattern_id');
            $table->index('external_calendar_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_events');
    }
}
