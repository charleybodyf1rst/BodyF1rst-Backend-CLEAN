<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrganizationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reward_id')->nullable();
            $table->string('name')->nullable();
            $table->string('logo')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->string('poc_name')->nullable();
            $table->string('poc_email')->nullable();
            $table->string('poc_phone')->nullable();
            $table->string('poc_title')->nullable();
            $table->tinyInteger('is_active')->default(1)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('reward_id')->references('id')->on('reward_programs')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('organizations');
    }
}
