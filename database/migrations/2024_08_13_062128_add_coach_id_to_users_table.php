<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCoachIdToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'coach_id')) {
                $table->unsignedBigInteger('coach_id')->after('organization_id')->nullable();
                $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('no action');
            }
            if (!Schema::hasColumn('users', 'profile_image')) {
                $table->string('profile_image')->after('password')->nullable();
            }
            if (!Schema::hasColumn('users', 'protein')) {
                $table->decimal('protein',10,2)->after('height')->default(0)->nullable();
            }
            if (!Schema::hasColumn('users', 'carb')) {
                $table->decimal('carb',10,2)->after('protein')->default(0)->nullable();
            }
            if (!Schema::hasColumn('users', 'calorie')) {
                $table->decimal('calorie',10,2)->after('carb')->default(0)->nullable();
            }
            if (!Schema::hasColumn('users', 'fat')) {
                $table->decimal('fat',10,2)->after('calorie')->default(0)->nullable();
            }
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
            //
        });
    }
}
