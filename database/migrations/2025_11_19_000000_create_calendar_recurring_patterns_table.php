<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarRecurringPatternsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_recurring_patterns', function (Blueprint $table) {
            $table->id();

            // Pattern Type
            $table->enum('frequency', [
                'daily',
                'weekly',
                'biweekly',
                'monthly',
                'yearly',
                'custom'
            ]);

            // Interval (e.g., every 2 weeks = interval: 2, frequency: weekly)
            $table->integer('interval')->default(1);

            // Days of week for weekly patterns (0 = Sunday, 6 = Saturday)
            $table->json('days_of_week')->nullable(); // [1, 3, 5] for Mon, Wed, Fri

            // Day of month for monthly patterns
            $table->integer('day_of_month')->nullable();

            // Month of year for yearly patterns
            $table->integer('month_of_year')->nullable();

            // End conditions
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('occurrence_count')->nullable(); // Number of occurrences
            $table->integer('occurrences_created')->default(0);

            // Exceptions (dates to skip)
            $table->json('exception_dates')->nullable();

            // Timezone
            $table->string('timezone')->default('UTC');

            $table->timestamps();

            // Indexes
            $table->index('frequency');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_recurring_patterns');
    }
}
