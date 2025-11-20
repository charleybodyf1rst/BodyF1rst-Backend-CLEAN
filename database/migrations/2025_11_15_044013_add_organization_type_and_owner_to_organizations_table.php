<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrganizationTypeAndOwnerToOrganizationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Add organization type if it doesn't exist
            if (!Schema::hasColumn('organizations', 'organization_type')) {
                $table->enum('organization_type', ['corporate', 'pt_studio'])->default('corporate')->after('name');
            }

            // Add owner_id for PT Studios if it doesn't exist
            if (!Schema::hasColumn('organizations', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->after('organization_type');
                $table->foreign('owner_id')->references('id')->on('coaches')->onDelete('set null');
            }

            // Add additional PT Studio fields if they don't exist
            if (!Schema::hasColumn('organizations', 'subscription_plan')) {
                $table->string('subscription_plan')->nullable()->after('owner_id');
            }
            if (!Schema::hasColumn('organizations', 'max_coaches')) {
                $table->integer('max_coaches')->nullable()->after('subscription_plan');
            }
            if (!Schema::hasColumn('organizations', 'max_clients')) {
                $table->integer('max_clients')->nullable()->after('max_coaches');
            }
            if (!Schema::hasColumn('organizations', 'status')) {
                $table->string('status')->default('active')->after('max_clients');
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
            // Remove foreign key first
            $table->dropForeign(['owner_id']);

            // Drop columns in reverse order
            $table->dropColumn([
                'status',
                'max_clients',
                'max_coaches',
                'subscription_plan',
                'owner_id',
                'organization_type'
            ]);
        });
    }
}
