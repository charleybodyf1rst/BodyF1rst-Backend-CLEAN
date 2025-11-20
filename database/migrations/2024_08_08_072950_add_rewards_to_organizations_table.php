<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRewardsToOrganizationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Check if the column exists before trying to drop it
            if (Schema::hasColumn('organizations', 'reward_id')) {
                // For SQLite, we need to check connection type
                $connection = Schema::getConnection();
                $driverName = $connection->getDriverName();

                if ($driverName !== 'sqlite') {
                    // Only drop foreign key if not using SQLite
                    $table->dropForeign(['reward_id']);
                }
                $table->dropColumn('reward_id');
            }

            // Add rewards column if it doesn't exist
            if (!Schema::hasColumn('organizations', 'rewards')) {
                $table->json('rewards')->after('poc_title')->nullable();
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
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('rewards');
        });
    }
}
