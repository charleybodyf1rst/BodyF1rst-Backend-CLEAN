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
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->string('redirect_url', 500)->nullable()->after('message');
            $table->json('metadata')->nullable()->after('redirect_url');

            $table->index('redirect_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex(['redirect_url']);
            $table->dropColumn(['redirect_url', 'metadata']);
        });
    }
};
