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
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('action', 100); // toggle_organization_status, send_payment_reminder, etc.
            $table->string('target_type', 50)->nullable(); // organization, coach, user
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('details')->nullable(); // Additional action details
            $table->timestamp('created_at');

            $table->index('admin_id');
            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
