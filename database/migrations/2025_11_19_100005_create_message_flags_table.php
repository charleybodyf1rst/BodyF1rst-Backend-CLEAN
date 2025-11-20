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
        Schema::create('message_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('flagged_by')->nullable();
            $table->enum('flagged_by_type', ['user', 'coach', 'admin', 'system'])->default('system');
            $table->enum('flag_type', ['profanity', 'nudity', 'harassment', 'spam', 'other'])->default('other');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'dismissed', 'actioned'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable(); // AI detection scores, etc.
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('admins')->onDelete('set null');

            // Indexes
            $table->index('message_id');
            $table->index('flag_type');
            $table->index('status');
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
        Schema::dropIfExists('message_flags');
    }
};
