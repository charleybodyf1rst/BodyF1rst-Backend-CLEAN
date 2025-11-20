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
        // Avatar Items Catalog
        Schema::create('avatar_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('item_type', [
                'clothing',
                'accessory',
                'hairstyle',
                'background',
                'effect',
                'badge',
                'emote',
                'skin'
            ]);
            $table->enum('rarity', ['common', 'uncommon', 'rare', 'epic', 'legendary'])->default('common');
            $table->string('thumbnail_url')->nullable();
            $table->string('asset_url')->nullable(); // 3D model or image
            $table->integer('unlock_cost')->default(0); // Points needed to unlock
            $table->enum('unlock_method', ['points', 'achievement', 'social_share', 'purchase', 'free'])->default('points');
            $table->boolean('is_premium')->default(false);
            $table->json('unlock_requirements')->nullable(); // Specific achievements or shares needed
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // User's Avatar Items (Inventory)
        Schema::create('user_avatar_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('avatar_item_id');
            $table->boolean('is_equipped')->default(false);
            $table->timestamp('unlocked_at');
            $table->string('unlocked_via')->nullable(); // How they got it
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('avatar_item_id')->references('id')->on('avatar_items')->onDelete('cascade');

            $table->unique(['user_id', 'avatar_item_id']);
        });

        // Social Sharing Rewards
        Schema::create('social_share_rewards', function (Blueprint $table) {
            $table->id();
            $table->enum('share_type', ['first_share', 'workout_share', 'nutrition_share', 'achievement_share', 'badge_share', 'progression_share']);
            $table->integer('points_reward')->default(0);
            $table->json('avatar_items_reward')->nullable(); // Array of avatar_item_ids
            $table->integer('max_claims_per_user')->default(1); // How many times can a user earn this?
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // User Reward Claims
        Schema::create('user_reward_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('social_share_reward_id');
            $table->unsignedBigInteger('social_share_id')->nullable(); // Which share earned this
            $table->integer('points_earned')->default(0);
            $table->json('items_earned')->nullable();
            $table->timestamp('claimed_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('social_share_reward_id')->references('id')->on('social_share_rewards')->onDelete('cascade');
            $table->foreign('social_share_id')->references('id')->on('social_shares')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_reward_claims');
        Schema::dropIfExists('social_share_rewards');
        Schema::dropIfExists('user_avatar_items');
        Schema::dropIfExists('avatar_items');
    }
};
