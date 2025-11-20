<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->bigInteger('model_id')->nullable();
            $table->string('model_type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->text('api_response')->nullable();
            $table->enum('module',['admin','app'])->default('app')->nullable();
            $table->enum('user_type',['All Users','Individual Users','Organizations'])->nullable();
            $table->text('cta_link')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('app_notifications');
    }
}
