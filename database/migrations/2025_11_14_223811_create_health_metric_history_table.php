<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHealthMetricHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('health_metric_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('metric_type', 50); // e.g., 'heart_rate', 'blood_pressure', 'weight'
            $table->decimal('metric_value', 10, 2);
            $table->string('metric_unit', 20)->nullable(); // e.g., 'bpm', 'mmHg', 'lbs'
            $table->json('metadata')->nullable(); // Additional data (e.g., {'systolic': 120, 'diastolic': 80})
            $table->enum('source', ['HealthKit', 'GoogleFit', 'Manual']);
            $table->timestamp('recorded_at');
            $table->timestamp('synced_at')->useCurrent();

            // Indexes
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'metric_type', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('health_metric_history');
    }
}
