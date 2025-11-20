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
        Schema::create('blocked_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blocker_id');
            $table->enum('blocker_type', ['user', 'coach', 'admin'])->default('user');
            $table->unsignedBigInteger('blocked_id');
            $table->enum('blocked_type', ['user', 'coach', 'admin'])->default('user');
            $table->text('reason')->nullable();
            $table->timestamps();

            // Composite unique key to prevent duplicate blocks
            $table->unique(['blocker_id', 'blocker_type', 'blocked_id', 'blocked_type'], 'blocked_user_unique');

            // Indexes
            $table->index(['blocker_id', 'blocker_type']);
            $table->index(['blocked_id', 'blocked_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blocked_users');
    }
};
