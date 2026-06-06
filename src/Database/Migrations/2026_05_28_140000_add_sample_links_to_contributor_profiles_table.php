<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSampleLinksToContributorProfilesTable extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table) {
            $table->json('sample_links')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table) {
            $table->dropColumn('sample_links');
        });
    }
}
