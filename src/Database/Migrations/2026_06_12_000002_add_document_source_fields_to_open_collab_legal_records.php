<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDocumentSourceFieldsToOpenCollabLegalRecords extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contract_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('source_document_id')->nullable()->after('content');
            $table->string('source_type', 40)->nullable()->after('source_document_id');
            $table->string('content_format', 40)->nullable()->after('source_type');
            $table->string('extraction_status', 40)->nullable()->after('content_format');
            $table->text('extraction_error')->nullable()->after('extraction_status');
        });

        Schema::table('oc_guideline_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('source_document_id')->nullable()->after('content');
            $table->string('source_type', 40)->nullable()->after('source_document_id');
            $table->string('content_format', 40)->nullable()->after('source_type');
            $table->string('extraction_status', 40)->nullable()->after('content_format');
            $table->text('extraction_error')->nullable()->after('extraction_status');
        });

        Schema::table('oc_contracts', function (Blueprint $table) {
            $table->string('title')->nullable()->after('site_id');
            $table->unsignedBigInteger('template_id')->nullable()->after('title');
            $table->unsignedBigInteger('document_id')->nullable()->after('template_id');
            $table->unsignedBigInteger('source_document_id')->nullable()->after('document_id');
            $table->string('source_type', 40)->default('manual')->after('source_document_id');
            $table->string('content_format', 40)->default('html')->after('content');
            $table->string('extraction_status', 40)->nullable()->after('content_format');
            $table->text('extraction_error')->nullable()->after('extraction_status');
            $table->unsignedBigInteger('issued_by_user_id')->nullable()->after('published_by');
            $table->timestamp('issued_at')->nullable()->after('issued_by_user_id');
        });

        Schema::table('oc_guidelines', function (Blueprint $table) {
            $table->string('title')->nullable()->after('site_id');
            $table->unsignedBigInteger('template_id')->nullable()->after('title');
            $table->unsignedBigInteger('document_id')->nullable()->after('template_id');
            $table->unsignedBigInteger('source_document_id')->nullable()->after('document_id');
            $table->string('source_type', 40)->default('manual')->after('source_document_id');
            $table->string('content_format', 40)->default('html')->after('content');
            $table->string('extraction_status', 40)->nullable()->after('content_format');
            $table->text('extraction_error')->nullable()->after('extraction_status');
            $table->unsignedBigInteger('published_by_user_id')->nullable()->after('published_by');
        });

        Schema::table('oc_user_contract_signatures', function (Blueprint $table) {
            $table->unsignedInteger('contract_version')->nullable()->after('contract_id');
            $table->text('user_agent')->nullable()->after('ip_address');
        });

        Schema::table('oc_user_guidelines_acknowledgements', function (Blueprint $table) {
            $table->unsignedBigInteger('guideline_id')->nullable()->after('site_id');
            $table->unsignedInteger('guideline_version')->nullable()->after('guideline_id');
            $table->timestamp('accepted_at')->nullable()->after('acknowledged_at');
            $table->string('accepted_ip', 64)->nullable()->after('accepted_at');
            $table->text('accepted_user_agent')->nullable()->after('accepted_ip');
        });
    }

    public function down(): void
    {
        foreach ([
            'oc_contract_templates' => ['source_document_id', 'source_type', 'content_format', 'extraction_status', 'extraction_error'],
            'oc_guideline_templates' => ['source_document_id', 'source_type', 'content_format', 'extraction_status', 'extraction_error'],
            'oc_contracts' => ['title', 'template_id', 'document_id', 'source_document_id', 'source_type', 'content_format', 'extraction_status', 'extraction_error', 'issued_by_user_id', 'issued_at'],
            'oc_guidelines' => ['title', 'template_id', 'document_id', 'source_document_id', 'source_type', 'content_format', 'extraction_status', 'extraction_error', 'published_by_user_id'],
            'oc_user_contract_signatures' => ['contract_version', 'user_agent'],
            'oc_user_guidelines_acknowledgements' => ['guideline_id', 'guideline_version', 'accepted_at', 'accepted_ip', 'accepted_user_agent'],
        ] as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
}
