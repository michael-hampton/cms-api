<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateConsentTables extends Migration
{
    public function up(): void
    {
        Schema::create('consent_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // e.g., 'marketing_email', 'analytics'
            $table->string('name', 255);
            $table->text('description');
            $table->enum('category', ['essential', 'functional', 'analytics', 'marketing', 'preferences']);
            $table->boolean('required')->default(false); // Cannot be opted out
            $table->integer('retention_days')->nullable(); // How long data is kept
            $table->json('data_purposes'); // What data is collected and why
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['code', 'is_active']);
        });

        Schema::create('member_consents', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('consent_type_id');
            $table->boolean('is_granted')->default(false);
            $table->enum('channel', ['web', 'email', 'api', 'admin']); // Where consent was given
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('consent_type_id')->references('id')->on('consent_types')->cascadeOnDelete();

            $table->unique(['member_id', 'consent_type_id'], 'unique_member_consent');
            $table->index(['member_id', 'is_granted']);
            $table->index('granted_at');
        });

        Schema::create('consent_audit_log', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('consent_type_id');
            $table->enum('action', ['granted', 'revoked', 'updated', 'expired']);
            $table->boolean('previous_state')->nullable();
            $table->boolean('new_state');
            $table->enum('source', ['web', 'email', 'api', 'admin', 'system']);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('admin_user_id')->nullable();
            $table->text('reason')->nullable(); // Why consent changed
            $table->json('metadata')->nullable();
            $table->timestamp('created_at'); // No updates allowed

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('consent_type_id')->references('id')->on('consent_types')->cascadeOnDelete();
            $table->foreign('admin_user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['member_id', 'created_at']);
            $table->index(['consent_type_id', 'created_at']);
            $table->index('action');
        });

        Schema::create('consent_notices', function ($table) {
            $table->id();
            $table->foreignId('site_id');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->text('content');
            $table->json('consent_types'); // Array of consent_type_ids this notice covers
            $table->enum('display_type', ['banner', 'modal', 'inline']);
            $table->json('display_rules')->nullable(); // Where/when to show
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->index(['site_id', 'is_active']);
        });

        Schema::create('member_consent_notices', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('consent_notice_id');
            $table->timestamp('shown_at');
            $table->timestamp('responded_at')->nullable();
            $table->enum('response', ['accepted', 'rejected', 'customized', 'dismissed'])->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('consent_notice_id')->references('id')->on('consent_notices')->cascadeOnDelete();

            $table->index(['member_id', 'shown_at']);
        });

        Schema::create('subscriber_consents', function ($table) {
            $table->id();
            $table->string('email', 255);
            $table->foreignId('consent_type_id');
            $table->boolean('is_granted')->default(false);
            $table->enum('channel', ['email', 'web', 'api']);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('consent_type_id')->references('id')->on('consent_types')->cascadeOnDelete();

            $table->unique(['email', 'consent_type_id'], 'unique_subscriber_consent');
            $table->index(['email', 'is_granted']);
        });

        Schema::create('data_processing_activities', function ($table) {
            $table->id();
            $table->string('name', 255);
            $table->text('purpose');
            $table->json('data_categories'); // What personal data
            $table->json('data_subjects'); // Who the data is about
            $table->json('recipients'); // Who receives the data
            $table->json('transfers')->nullable(); // International transfers
            $table->integer('retention_period_days');
            $table->json('security_measures');
            $table->json('related_consent_types'); // Which consent types enable this
            $table->timestamps();
        });

        Schema::create('consent_withdrawal_requests', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->enum('type', ['specific_consent', 'all_marketing', 'complete_deletion']);
            $table->json('consent_types')->nullable(); // Specific consents to revoke
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled']);
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('processed_by')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
