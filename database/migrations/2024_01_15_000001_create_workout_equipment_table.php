<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workout_equipment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_id');
            $table->string('equipment_name');
            $table->string('equipment_icon')->nullable();
            $table->text('equipment_description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->foreign('workout_id')->references('id')->on('workouts')->onDelete('cascade');
            $table->index(['workout_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workout_equipment');
    }
};
