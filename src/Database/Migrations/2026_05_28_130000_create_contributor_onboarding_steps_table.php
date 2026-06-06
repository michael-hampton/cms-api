<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateContributorOnboardingStepsTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_contributor_onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('site_id');
            $table->string('step', 64);
            $table->string('status', 32)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'site_id', 'step'], 'oc_onboarding_steps_user_site_step');
            $table->index(['site_id', 'step', 'status'], 'oc_onboarding_steps_site_step_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_contributor_onboarding_steps');
    }
}
