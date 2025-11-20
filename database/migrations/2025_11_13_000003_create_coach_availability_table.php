<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoachAvailabilityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coach_availability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->date('specific_date')->nullable()->comment('For one-time availability overrides');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('cascade');

            // Indexes
            $table->index('coach_id');
            $table->index('day_of_week');
            $table->index('is_available');
            $table->index(['coach_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coach_availability');
    }
}
