<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToAssignPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assign_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_by')->after('id')->nullable();
            $table->enum('uploader', ['Admin', 'Coach'])->after('uploaded_by')->nullable();

            $table->dropForeign(['coach_id']);
            $table->dropColumn('coach_id');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assign_plans', function (Blueprint $table) {
            $table->dropColumn('uploaded_by');
            $table->dropColumn('uploader');
        });
    }
}
