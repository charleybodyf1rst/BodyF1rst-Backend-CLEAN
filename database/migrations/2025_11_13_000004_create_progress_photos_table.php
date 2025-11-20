<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProgressPhotosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('photo_url');
            $table->enum('photo_type', ['front', 'back', 'side', 'other'])->default('front');
            $table->date('taken_at');
            $table->decimal('weight', 8, 2)->nullable()->comment('Weight in kg at the time of photo');
            $table->text('notes')->nullable();
            $table->boolean('is_public')->default(false)->comment('Whether coach can view this photo');
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index('taken_at');
            $table->index(['user_id', 'taken_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('progress_photos');
    }
}
