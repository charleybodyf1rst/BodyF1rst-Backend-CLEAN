<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoachDashboardLayoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coach_dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->json('widgets'); // Store widget configuration as JSON
            $table->string('dashboard_type')->default('main'); // main, fitness, nutrition, cbt, etc.
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint: one layout per coach per dashboard type
            $table->unique(['coach_id', 'dashboard_type']);

            // Index for faster queries
            $table->index('coach_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coach_dashboard_layouts');
    }
}
