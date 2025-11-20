<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateBodyPointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('body_points', function (Blueprint $table) {
            $table->id();
            $table->string('meta_key')->nullable();
            $table->json('meta_value')->nullable();
            $table->timestamps();
        });

        // Insert default data
        DB::table('body_points')->insert([
            [
                'meta_key' => 'points',
                'meta_value' => json_encode([
                    'signup_compeletion' => [
                        'profile' => 10,
                    ],
                    'workout_and_exercise' => [
                        'accccountability_none' => 5,
                        'accccountability_low' => 5,
                        'accccountability_medium' => 5,
                        'accccountability_high' => 5,
                    ],
                    'daily_meal' => [
                        'accccountability_none' => 5,
                        'accccountability_low' => 5,
                        'accccountability_medium' => 5,
                        'accccountability_high' => 5,
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('body_points');
    }
}
