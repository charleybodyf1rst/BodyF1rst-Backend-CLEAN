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
        Schema::create('message_edit_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->text('original_content');
            $table->text('new_content');
            $table->timestamp('edited_at');
            $table->timestamps();

            // Foreign keys
            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');

            // Indexes
            $table->index('message_id');
            $table->index('edited_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('message_edit_history');
    }
};
