<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddContributorAuthorSyncMetadata extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('author_id')->nullable()->after('user_id');
            $table->string('author_sync_status', 50)->nullable()->after('author_id');
            $table->timestamp('author_last_synced_at')->nullable()->after('author_sync_status');
            $table->unsignedBigInteger('author_last_synced_by')->nullable()->after('author_last_synced_at');
            $table->index('author_id', 'oc_contributor_profiles_author_id_idx');
        });

        Schema::table('authors', function (Blueprint $table): void {
            $table->json('overridden_fields')->nullable()->after('is_guest');
            $table->string('last_updated_by_type', 50)->nullable()->after('overridden_fields');
            $table->unsignedBigInteger('last_updated_by_id')->nullable()->after('last_updated_by_type');
        });

        Schema::create('oc_contributor_author_sync_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contributor_profile_id')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('actor_type', 50);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event', 80);
            $table->json('fields')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['contributor_profile_id', 'created_at'], 'oc_author_sync_profile_created_idx');
            $table->index(['author_id', 'created_at'], 'oc_author_sync_author_created_idx');
            $table->index(['event', 'created_at'], 'oc_author_sync_event_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_contributor_author_sync_audits');

        Schema::table('authors', function (Blueprint $table): void {
            $table->dropColumn([
                'overridden_fields',
                'last_updated_by_type',
                'last_updated_by_id',
            ]);
        });

        Schema::table('oc_contributor_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'author_id',
                'author_sync_status',
                'author_last_synced_at',
                'author_last_synced_by',
            ]);
        });
    }
}
