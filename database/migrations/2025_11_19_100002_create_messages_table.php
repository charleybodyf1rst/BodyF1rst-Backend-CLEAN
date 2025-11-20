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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('sender_id');
            $table->enum('sender_type', ['user', 'coach', 'admin'])->default('user');
            $table->unsignedBigInteger('reply_to_message_id')->nullable(); // For threads
            $table->text('message')->nullable(); // Encrypted message content
            $table->text('message_encrypted')->nullable(); // Encrypted message content
            $table->json('attachments')->nullable(); // File attachments
            $table->enum('message_type', ['text', 'image', 'video', 'audio', 'file', 'voice', 'gif'])->default('text');
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_forwarded')->default(false);
            $table->boolean('is_scheduled')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('reply_to_message_id')->references('id')->on('messages')->onDelete('set null');

            // Indexes
            $table->index('conversation_id');
            $table->index(['sender_id', 'sender_type']);
            $table->index('created_at');
            $table->index('is_pinned');
            $table->index('is_scheduled');
            $table->fullText(['message']); // For message search
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
