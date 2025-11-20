<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoachChallengesTable extends Migration
{
    public function up()
    {
        Schema::create('coach_challenges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('challenge_type');
            $table->integer('duration_days');
            $table->json('daily_tasks')->nullable();
            $table->json('rules')->nullable();
            $table->json('rewards')->nullable();
            $table->json('tags')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('coach_id');
            $table->index('challenge_type');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('coach_challenges');
    }
}
