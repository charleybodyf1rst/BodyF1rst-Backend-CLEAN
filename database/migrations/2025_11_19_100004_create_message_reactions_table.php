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
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('user_type', ['user', 'coach', 'admin'])->default('user');
            $table->string('reaction', 10); // Emoji or reaction type
            $table->timestamps();

            // Foreign keys
            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');

            // Composite unique key to prevent duplicate reactions
            $table->unique(['message_id', 'user_id', 'user_type', 'reaction'], 'message_reaction_unique');

            // Indexes
            $table->index(['user_id', 'user_type']);
            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('message_reactions');
    }
};
