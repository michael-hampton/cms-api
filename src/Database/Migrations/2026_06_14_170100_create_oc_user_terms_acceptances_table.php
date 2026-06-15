<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcUserTermsAcceptancesTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_user_terms_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('terms_version_id');
            $table->string('rendered_hash', 64);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('accepted_via', 50)->default('onboarding');
            $table->timestamps();

            $table->unique(['site_id', 'user_id', 'terms_version_id'], 'oc_user_terms_acceptances_unique');
            $table->index(['site_id', 'user_id'], 'oc_user_terms_acceptances_user_idx');
            $table->index('terms_version_id', 'oc_user_terms_acceptances_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_user_terms_acceptances');
    }
}
