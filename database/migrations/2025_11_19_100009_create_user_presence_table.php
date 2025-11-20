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
        Schema::create('user_presence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('user_type', ['user', 'coach', 'admin'])->default('user');
            $table->enum('status', ['online', 'offline', 'away', 'busy'])->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('device_type')->nullable();
            $table->timestamps();

            // Composite unique key
            $table->unique(['user_id', 'user_type'], 'user_presence_unique');

            // Indexes
            $table->index(['user_id', 'user_type']);
            $table->index('status');
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_presence');
    }
};
