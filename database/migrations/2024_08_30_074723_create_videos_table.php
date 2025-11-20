<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->enum('uploader',['Admin','Coach'])->default('Admin')->nullable();
            $table->string('video_title')->nullable();
            $table->string('video_file')->nullable();
            $table->text('video_url')->nullable();
            $table->string('video_thumbnail')->nullable();
            $table->integer('video_duration')->nullable();
            $table->string('video_format')->nullable();
            $table->enum('type',['Public','Private'])->default('Public')->nullable();
            $table->tinyInteger('is_active')->default(1)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('videos');
    }
}
