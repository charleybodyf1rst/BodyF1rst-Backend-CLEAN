<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChallengeLibraryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('challenge_library', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_admin_id'); // Admin who created it
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('challenge_type'); // steps, workout, nutrition, mindset, etc.
            $table->integer('duration_days');
            $table->json('daily_tasks')->nullable(); // Array of tasks for each day
            $table->json('rules')->nullable(); // Challenge rules
            $table->json('rewards')->nullable(); // Rewards for completion
            $table->json('tags')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('clone_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by_admin_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('created_by_admin_id');
            $table->index('challenge_type');
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
        Schema::dropIfExists('challenge_library');
    }
}
