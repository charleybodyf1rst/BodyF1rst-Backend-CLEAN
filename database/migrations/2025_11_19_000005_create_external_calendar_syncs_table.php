<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExternalCalendarSyncsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('external_calendar_syncs', function (Blueprint $table) {
            $table->id();

            // User relationship
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('coach_id')->nullable();

            // Calendar provider
            $table->enum('provider', [
                'google',
                'apple',
                'outlook',
                'office365',
                'ical'
            ]);

            // Provider details
            $table->string('provider_calendar_id')->nullable();
            $table->string('provider_email')->nullable();

            // Sync settings
            $table->boolean('sync_enabled')->default(true);
            $table->enum('sync_direction', [
                'two_way',
                'to_external',
                'from_external'
            ])->default('two_way');

            // OAuth tokens (encrypted)
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            // Sync status
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->enum('sync_status', [
                'active',
                'paused',
                'error',
                'disconnected'
            ])->default('active');

            $table->text('last_error')->nullable();
            $table->integer('sync_error_count')->default(0);

            // Sync preferences
            $table->json('event_types_to_sync')->nullable(); // Which event types to sync
            $table->boolean('sync_past_events')->default(false);
            $table->integer('sync_days_ahead')->default(90);

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index('coach_id');
            $table->index('provider');
            $table->index('sync_status');
            $table->index('next_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('external_calendar_syncs');
    }
}
