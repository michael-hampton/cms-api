<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionPricingChangeTransitions extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_pricing_change_transitions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('subscription_pricing_change_id');

            $table->unsignedBigInteger('old_subscription_id');
            $table->unsignedBigInteger('new_subscription_id')->nullable();

            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('site_id');

            $table->unsignedBigInteger('old_plan_id');
            $table->unsignedBigInteger('new_plan_id');

            $table->decimal('old_price', 10, 2);
            $table->decimal('new_price', 10, 2);
            $table->string('currency', 3)->default('GBP');

            $table->string('old_stripe_subscription_id')->nullable();
            $table->string('new_stripe_subscription_id')->nullable();

            $table->boolean('itd_required')->default(false);
            $table->string('itd_letter_code')->nullable();
            $table->string('communication_dedupe_key')->nullable();

            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('subscription_pricing_change_id')
                ->references('id')
                ->on('subscription_pricing_changes');

            $table->foreign('old_subscription_id')
                ->references('id')
                ->on('subscriptions');

            $table->foreign('new_subscription_id')
                ->references('id')
                ->on('subscriptions');

            $table->index(['subscription_pricing_change_id', 'status'], 'spct_change_status_idx');
            $table->index(['old_subscription_id'], 'spct_old_subscription_idx');
            $table->index(['new_subscription_id'], 'spct_new_subscription_idx');
            $table->index(['communication_dedupe_key'], 'spct_dedupe_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
