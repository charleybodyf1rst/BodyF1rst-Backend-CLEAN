<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWeightGoalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('weight_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('start_weight', 6, 2);
            $table->decimal('target_weight', 6, 2);
            $table->string('unit')->default('lbs'); // lbs or kg
            $table->date('start_date');
            $table->date('target_date');
            $table->string('goal_type'); // lose, gain, maintain
            $table->decimal('weekly_target', 4, 2)->default(0); // Weekly change target
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_active');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('weight_goals');
    }
}
