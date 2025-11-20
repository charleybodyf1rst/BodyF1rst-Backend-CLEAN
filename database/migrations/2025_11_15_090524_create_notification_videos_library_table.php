<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationVideosLibraryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notification_videos_library', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_admin_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_url');
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('category')->nullable(); // reminders, announcements, tips, updates, etc.
            $table->json('tags')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('view_count')->default(0);
            $table->integer('clone_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('created_by_admin_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('created_by_admin_id');
            $table->index('category');
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
        Schema::dropIfExists('notification_videos_library');
    }
}
