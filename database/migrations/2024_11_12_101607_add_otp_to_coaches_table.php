<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOtpToCoachesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->string('reset_token')->after('profile_image')->nullable();
        });
        Schema::table('admins', function (Blueprint $table) {
            $table->string('reset_token')->after('profile_image')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn('reset_token');
        });
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('reset_token');
        });
    }
}
