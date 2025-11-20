<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMobileDevicesTable extends Migration
{
    public function up()
    {
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('device_token')->unique();
            $table->enum('device_type', ['ios', 'android']);
            $table->string('device_name')->nullable();
            $table->string('app_version')->nullable();
            $table->string('os_version')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('device_token');
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('push_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('device_id')->constrained('mobile_devices')->onDelete('cascade');
            $table->boolean('workouts')->default(true);
            $table->boolean('meals')->default(true);
            $table->boolean('messages')->default(true);
            $table->boolean('reminders')->default(true);
            $table->boolean('progress')->default(true);
            $table->boolean('social')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('device_id');
        });

        Schema::create('mobile_error_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('error_type');
            $table->text('error_message');
            $table->text('stack_trace')->nullable();
            $table->string('app_version');
            $table->json('device_info')->nullable();
            $table->timestamp('created_at');

            $table->index('user_id');
            $table->index('error_type');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mobile_error_logs');
        Schema::dropIfExists('push_notification_settings');
        Schema::dropIfExists('mobile_devices');
    }
}
