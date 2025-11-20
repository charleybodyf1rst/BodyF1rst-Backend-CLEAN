<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoachFitnessVideosTable extends Migration
{
    public function up()
    {
        Schema::create('coach_fitness_videos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_url');
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('category')->nullable();
            $table->string('difficulty_level')->nullable();
            $table->json('tags')->nullable();
            $table->integer('view_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('coach_id');
            $table->index('category');
            $table->index('difficulty_level');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('coach_fitness_videos');
    }
}
