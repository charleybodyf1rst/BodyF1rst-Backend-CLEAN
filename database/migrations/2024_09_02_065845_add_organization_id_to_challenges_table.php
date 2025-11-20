<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrganizationIdToChallengesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->after('id')->nullable();
            $table->unsignedBigInteger('coach_id')->after('organization_id')->nullable();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('no action');
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn('organization_id');
            $table->dropColumn('coach_id');
        });
    }
}
