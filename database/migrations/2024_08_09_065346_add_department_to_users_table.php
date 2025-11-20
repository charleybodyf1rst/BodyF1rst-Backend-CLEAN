<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDepartmentToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Check if the column exists before trying to drop it
            if (Schema::hasColumn('users', 'department_id')) {
                // For SQLite, we need to check connection type
                $connection = Schema::getConnection();
                $driverName = $connection->getDriverName();

                if ($driverName !== 'sqlite') {
                    // Only drop foreign key if not using SQLite
                    $table->dropForeign(['department_id']);
                }
                $table->dropColumn('department_id');
            }

            // Add new columns if they don't exist
            if (!Schema::hasColumn('users', 'department')) {
                $table->string('department')->after('organization_id')->nullable();
            }
            if (!Schema::hasColumn('users', 'dietary_restrictions')) {
                $table->json('dietary_restrictions')->after('accountability')->nullable();
            }
            if (!Schema::hasColumn('users', 'equipment_preferences')) {
                $table->json('equipment_preferences')->after('dietary_restrictions')->nullable();
            }
            if (!Schema::hasColumn('users', 'training_preferences')) {
                $table->json('training_preferences')->after('equipment_preferences')->nullable();
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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('department');
            $table->dropColumn('dietary_restrictions');
            $table->dropColumn('equipment_preferences');
            $table->dropColumn('training_preferences');
        });
    }
}
