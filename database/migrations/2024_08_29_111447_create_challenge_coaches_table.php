<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChallengeCoachesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('challenge_coaches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('challenge_id')->nullable();
            $table->unsignedBigInteger('coach_id')->nullable();
            $table->timestamps();

            $table->foreign('challenge_id')->references('id')->on('challenges')->onDelete('no action');
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('no action');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('challenge_coaches');
    }
}
