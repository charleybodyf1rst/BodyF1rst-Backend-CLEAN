<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContractStartAndEndDateToOrganizationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'contract_start_date')) {
                $table->date('contract_start_date')->after('website')->nullable();
            }
            if (!Schema::hasColumn('organizations', 'contract_end_date')) {
                $table->date('contract_end_date')->after('contract_start_date')->nullable();
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
            $table->dropColumn('contract_start_date');
            $table->dropColumn('contract_end_date');
        });
    }
}
