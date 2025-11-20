<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleAndStudioFieldsToCoachesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('coaches', function (Blueprint $table) {
            // Add role field for PT Studio hierarchy if it doesn't exist
            if (!Schema::hasColumn('coaches', 'role')) {
                $table->enum('role', ['lead_trainer', 'coach', 'assistant_coach'])->default('coach')->after('email');
            }

            // Add studio_id to associate coaches with PT Studios if it doesn't exist
            if (!Schema::hasColumn('coaches', 'studio_id')) {
                $table->unsignedBigInteger('studio_id')->nullable()->after('role');
                $table->foreign('studio_id')->references('id')->on('organizations')->onDelete('set null');
            }

            // Add specialties field for coach expertise if it doesn't exist
            if (!Schema::hasColumn('coaches', 'specialties')) {
                $table->json('specialties')->nullable()->after('studio_id');
            }

            // Add bio/description for coach profile if it doesn't exist
            if (!Schema::hasColumn('coaches', 'bio')) {
                $table->text('bio')->nullable()->after('specialties');
            }

            // Add hourly rate for private lessons if it doesn't exist
            if (!Schema::hasColumn('coaches', 'hourly_rate')) {
                $table->decimal('hourly_rate', 8, 2)->nullable()->after('bio');
            }

            // Add certification/credentials if it doesn't exist
            if (!Schema::hasColumn('coaches', 'certifications')) {
                $table->text('certifications')->nullable()->after('hourly_rate');
            }

            // Add years of experience if it doesn't exist
            if (!Schema::hasColumn('coaches', 'years_experience')) {
                $table->integer('years_experience')->nullable()->after('certifications');
            }

            // Add availability status if it doesn't exist
            if (!Schema::hasColumn('coaches', 'is_accepting_clients')) {
                $table->boolean('is_accepting_clients')->default(true)->after('years_experience');
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
        Schema::table('coaches', function (Blueprint $table) {
            // Remove foreign key first
            $table->dropForeign(['studio_id']);

            // Drop columns in reverse order
            $table->dropColumn([
                'is_accepting_clients',
                'years_experience',
                'certifications',
                'hourly_rate',
                'bio',
                'specialties',
                'studio_id',
                'role'
            ]);
        });
    }
}
