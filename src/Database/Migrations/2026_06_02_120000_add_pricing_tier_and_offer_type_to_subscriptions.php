<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPricingTierAndOfferTypeToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_plan_pricing_id')
                ->nullable()
                ->after('price_paid_cents');

            $table->string('offer_type', 32)
                ->nullable()
                ->after('subscription_plan_pricing_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_plan_pricing_id',
                'offer_type',
            ]);
        });
    }
}
