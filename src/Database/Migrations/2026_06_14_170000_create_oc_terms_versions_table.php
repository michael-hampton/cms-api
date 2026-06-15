<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcTermsVersionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_terms_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('semantic_version', 32);
            $table->string('title', 255);
            $table->string('source_format', 20)->default('html');
            $table->longText('source_content');
            $table->string('rendered_format', 20)->default('html');
            $table->longText('rendered_content')->nullable();
            $table->string('rendered_hash', 64)->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_material_change')->default(false);
            $table->text('change_summary')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->string('source_type', 50)->default('manual');
            $table->string('extraction_status', 30)->default('not_required');
            $table->text('extraction_error')->nullable();
            $table->unsignedBigInteger('supersedes_terms_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by_user_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('archived_by_user_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            $table->unique(['site_id', 'semantic_version'], 'oc_terms_versions_site_semver_uq');
            $table->unique(['site_id', 'rendered_hash'], 'oc_terms_versions_site_hash_uq');
            $table->index(['site_id', 'status', 'published_at'], 'oc_terms_versions_current_idx');
            $table->index('document_id', 'oc_terms_versions_document_idx');
            $table->index('source_document_id', 'oc_terms_versions_source_document_idx');
            $table->index('supersedes_terms_version_id', 'oc_terms_versions_supersedes_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_terms_versions');
    }
}
