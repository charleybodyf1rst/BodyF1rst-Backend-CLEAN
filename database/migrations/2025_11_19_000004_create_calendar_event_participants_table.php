<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarEventParticipantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_event_participants', function (Blueprint $table) {
            $table->id();

            // Event relationship
            $table->unsignedBigInteger('calendar_event_id');

            // Participant
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('coach_id')->nullable();
            $table->string('email')->nullable(); // For external participants

            // Role
            $table->enum('role', [
                'organizer',
                'participant',
                'required',
                'optional'
            ])->default('participant');

            // Response
            $table->enum('response_status', [
                'pending',
                'accepted',
                'declined',
                'tentative',
                'no_response'
            ])->default('pending');

            $table->timestamp('response_at')->nullable();
            $table->text('response_note')->nullable();

            // Notifications
            $table->boolean('receive_reminders')->default(true);
            $table->boolean('reminder_sent')->default(false);

            $table->timestamps();

            // Foreign Keys
            $table->foreign('calendar_event_id')->references('id')->on('calendar_events')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('cascade');

            // Indexes
            $table->index('calendar_event_id');
            $table->index('user_id');
            $table->index('coach_id');
            $table->index('response_status');

            // Unique constraint (prevent duplicate participants)
            $table->unique(['calendar_event_id', 'user_id', 'coach_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_event_participants');
    }
}
