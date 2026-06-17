<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcImageSubmissionEvidenceTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_image_submission_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->index();
            $table->unsignedInteger('cms_image_id')->index();
            $table->foreignId('contributor_user_id')->index();
            $table->foreignId('contributor_profile_id')->nullable()->index();

            // Snapshot of contributor-declared values at submission time — immutable
            $table->string('cms_image_rights_value', 64);
            $table->string('name_submitted');
            $table->text('alt_text_submitted');
            $table->string('credit_submitted')->default('');

            // Terms and attestation version references (nullable until terms are enforced)
            $table->unsignedInteger('terms_version_id')->nullable();
            $table->unsignedInteger('attestation_version_id')->nullable();

            // Declarations
            $table->boolean('rights_confirmation')->default(false);
            $table->boolean('ai_generated')->default(false);
            $table->boolean('sponsored_content')->default(false);
            $table->boolean('affiliate_content')->default(false);

            // Audit / idempotency
            $table->string('request_correlation_id', 128)->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('submitted_at');
            $table->timestamps();

            // Foreign keys
            $table->foreign('contributor_user_id')->references('id')->on('users');
            $table->foreign('site_id')->references('id')->on('sites');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
