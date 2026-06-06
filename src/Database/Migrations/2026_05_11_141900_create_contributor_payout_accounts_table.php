<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateContributorPayoutAccountsTable extends Migration
{
    public function up(): void
    {
        Schema::create('contributor_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('provider');

            // Stripe-specific identifier (nullable for other providers)
            $table->string('stripe_account_id')->nullable();

            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);

            $table->timestamp('onboarding_completed_at')->nullable();
            $table->json('requirements_due_json')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->unique(['stripe_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributor_payout_accounts');
    }
}

