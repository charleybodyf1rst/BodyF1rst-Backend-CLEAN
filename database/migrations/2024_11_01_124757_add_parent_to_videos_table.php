<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddParentToVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->after('id')->nullable();

            $table->foreign('parent_id')->references('id')->on('videos')->onDelete('no action');
        });
        Schema::table('exercises', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->after('id')->nullable();

            $table->foreign('parent_id')->references('id')->on('exercises')->onDelete('no action');
        });
        Schema::table('workouts', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->after('id')->nullable();

            $table->foreign('parent_id')->references('id')->on('workouts')->onDelete('no action');
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->after('id')->nullable();

            $table->foreign('parent_id')->references('id')->on('plans')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('parent_id');
        });
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('parent_id');
        });
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn('parent_id');
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('parent_id');
        });
    }
}
