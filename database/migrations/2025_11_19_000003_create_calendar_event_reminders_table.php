<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarEventRemindersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_event_reminders', function (Blueprint $table) {
            $table->id();

            // Event relationship
            $table->unsignedBigInteger('calendar_event_id');

            // Reminder settings
            $table->integer('minutes_before'); // 1440 = 1 day, 60 = 1 hour, 15 = 15 minutes
            $table->dateTime('scheduled_for');

            // Delivery method
            $table->enum('method', [
                'push',
                'email',
                'sms',
                'in_app'
            ])->default('push');

            // Status
            $table->enum('status', [
                'pending',
                'sent',
                'failed',
                'cancelled'
            ])->default('pending');

            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();

            // Retry logic
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);

            $table->timestamps();

            // Foreign Keys
            $table->foreign('calendar_event_id')->references('id')->on('calendar_events')->onDelete('cascade');

            // Indexes
            $table->index('calendar_event_id');
            $table->index('scheduled_for');
            $table->index('status');
            $table->index(['status', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_event_reminders');
    }
}
