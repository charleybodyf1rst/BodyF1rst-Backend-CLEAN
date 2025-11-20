<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarBlockedTimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_blocked_times', function (Blueprint $table) {
            $table->id();

            // Who blocked this time
            $table->unsignedBigInteger('coach_id');

            // Time range
            $table->dateTime('start_time');
            $table->dateTime('end_time');

            // Reason
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            // Type
            $table->enum('block_type', [
                'unavailable',
                'break',
                'personal',
                'vacation',
                'holiday',
                'other'
            ])->default('unavailable');

            // Recurring
            $table->unsignedBigInteger('recurring_pattern_id')->nullable();

            // Color for calendar display
            $table->string('color')->default('#6B7280'); // Gray

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('cascade');
            $table->foreign('recurring_pattern_id')->references('id')->on('calendar_recurring_patterns')->onDelete('set null');

            // Indexes
            $table->index('coach_id');
            $table->index('start_time');
            $table->index('end_time');
            $table->index(['coach_id', 'start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_blocked_times');
    }
}
