<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('friend_id');
            $table->enum('status', ['pending', 'accepted', 'blocked'])->default('pending');
            $table->enum('connection_source', ['manual', 'email', 'phone', 'social_media'])->default('manual');
            $table->boolean('can_view_progression')->default(true);
            $table->boolean('can_message')->default(true);
            $table->boolean('share_workouts')->default(false);
            $table->boolean('share_nutrition')->default(false);
            $table->boolean('share_achievements')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('friend_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint to prevent duplicate connections
            $table->unique(['user_id', 'friend_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_connections');
    }
};
