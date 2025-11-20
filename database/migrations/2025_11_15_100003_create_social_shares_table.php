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
        Schema::create('social_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('share_type', ['workout', 'nutrition', 'achievement', 'badge', 'progression', 'challenge']);
            $table->unsignedBigInteger('shareable_id'); // ID of the shared item
            $table->string('shareable_type'); // Model class of shared item
            $table->text('caption')->nullable();
            $table->enum('platform', ['internal', 'facebook', 'instagram', 'twitter', 'tiktok', 'linkedin'])->default('internal');
            $table->string('external_post_id')->nullable(); // ID from social platform
            $table->string('external_post_url')->nullable();
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->integer('shares_count')->default(0);
            $table->boolean('rewarded')->default(false); // Whether user received avatar items
            $table->json('reward_items')->nullable(); // Items earned from this share
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Polymorphic index
            $table->index(['shareable_id', 'shareable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_shares');
    }
};
