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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('daily_calorie_goal', 8, 2)->default(2000)->after('email');
            $table->decimal('daily_protein_goal', 8, 2)->default(150)->after('daily_calorie_goal');
            $table->decimal('daily_carbs_goal', 8, 2)->default(200)->after('daily_protein_goal');
            $table->decimal('daily_fats_goal', 8, 2)->default(65)->after('daily_carbs_goal');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'daily_calorie_goal',
                'daily_protein_goal', 
                'daily_carbs_goal',
                'daily_fats_goal'
            ]);
        });
    }
};
