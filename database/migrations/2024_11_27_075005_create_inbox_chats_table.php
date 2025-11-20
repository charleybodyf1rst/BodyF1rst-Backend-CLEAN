<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInboxChatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inbox_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("inbox_id")->nullable();
            $table->unsignedBigInteger("sender_id")->nullable();
            $table->enum("sender_role",['User','Coach'])->nullable();
            $table->text("message")->nullable();
            $table->text("attachment")->nullable();
            $table->tinyInteger("has_read")->default(0)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('inbox_id')->references('id')->on('inboxes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inbox_chats');
    }
}
