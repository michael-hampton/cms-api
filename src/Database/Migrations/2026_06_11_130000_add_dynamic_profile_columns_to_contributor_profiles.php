<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDynamicProfileColumnsToContributorProfiles extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table): void {
            if (!Schema::hasColumn('oc_contributor_profiles', 'display_name')) {
                $table->string('display_name', 255)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('oc_contributor_profiles', 'timezone')) {
                $table->string('timezone', 50)->nullable()->after('tax_country');
            }

            if (!Schema::hasColumn('oc_contributor_profiles', 'portfolio_url')) {
                $table->string('portfolio_url', 500)->nullable()->after('sample_links');
            }

            if (!Schema::hasColumn('oc_contributor_profiles', 'linkedin_url')) {
                $table->string('linkedin_url', 500)->nullable()->after('portfolio_url');
            }

            if (!Schema::hasColumn('oc_contributor_profiles', 'twitter_url')) {
                $table->string('twitter_url', 500)->nullable()->after('linkedin_url');
            }

            if (!Schema::hasColumn('oc_contributor_profiles', 'instagram_url')) {
                $table->string('instagram_url', 500)->nullable()->after('twitter_url');
            }

            if (!Schema::hasColumn('oc_contributor_profiles', 'tiktok_url')) {
                $table->string('tiktok_url', 500)->nullable()->after('instagram_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table): void {
            foreach ([
                'tiktok_url',
                'instagram_url',
                'twitter_url',
                'linkedin_url',
                'portfolio_url',
                'timezone',
                'display_name',
            ] as $column) {
                if (Schema::hasColumn('oc_contributor_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
