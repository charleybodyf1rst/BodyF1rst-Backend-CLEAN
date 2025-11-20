<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBadgesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'streak_master'
            $table->string('name'); // e.g., 'Streak Master'
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Badge icon/image
            $table->string('tier')->default('bronze'); // bronze, silver, gold, platinum
            $table->integer('points_value')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index('tier');
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
        Schema::dropIfExists('badges');
    }
}
