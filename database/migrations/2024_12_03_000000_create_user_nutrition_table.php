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
        Schema::create('user_nutrition', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            
            // Calorie tracking
            $table->decimal('calories_consumed', 8, 2)->default(0);
            $table->decimal('calories_target', 8, 2)->default(2000);
            
            // Macro tracking
            $table->decimal('protein_consumed', 8, 2)->default(0);
            $table->decimal('protein_target', 8, 2)->default(150);
            $table->decimal('carbs_consumed', 8, 2)->default(0);
            $table->decimal('carbs_target', 8, 2)->default(200);
            $table->decimal('fats_consumed', 8, 2)->default(0);
            $table->decimal('fats_target', 8, 2)->default(65);
            
            // Health app sync tracking
            $table->string('sync_source')->nullable(); // apple_health, google_fit, myfitnesspal
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_nutrition');
    }
};
