<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFitnessVideosLibraryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fitness_videos_library', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_admin_id'); // Admin who created it
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_url'); // S3 or video hosting URL
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable(); // Video length
            $table->string('category')->nullable(); // cardio, strength, yoga, pilates, etc.
            $table->string('difficulty_level')->nullable(); // beginner, intermediate, advanced
            $table->json('tags')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('view_count')->default(0);
            $table->integer('clone_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by_admin_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('created_by_admin_id');
            $table->index('category');
            $table->index('difficulty_level');
            $table->index('is_featured');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fitness_videos_library');
    }
}
