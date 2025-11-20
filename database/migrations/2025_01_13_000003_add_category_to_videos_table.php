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
        Schema::table('videos', function (Blueprint $table) {
            $table->enum('category', ['fitness', 'nutrition', 'wellness', 'education', 'other'])
                  ->default('fitness')
                  ->after('tags');

            $table->text('description')->nullable()->after('video_title');

            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'description']);
        });
    }
};
