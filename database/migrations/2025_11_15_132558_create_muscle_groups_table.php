<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMuscleGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('muscle_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Chest, Back, Legs, etc.
            $table->string('category'); // primary, secondary
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('muscle_group_parent')->nullable(); // For sub-groups
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('muscle_groups');
    }
}
