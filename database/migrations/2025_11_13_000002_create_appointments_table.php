<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppointmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('client_id');
            $table->string('title');
            $table->enum('type', ['session', 'check-in', 'consultation', 'assessment', 'other'])->default('session');
            $table->dateTime('scheduled_at');
            $table->dateTime('end_time')->nullable();
            $table->integer('duration')->comment('Duration in minutes');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no-show', 'rescheduled'])->default('scheduled');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('coach_id');
            $table->index('client_id');
            $table->index('scheduled_at');
            $table->index('status');
            $table->index(['coach_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appointments');
    }
}
