<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddSubscriptionSupportToVouchers extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function ($table) {
            $table->boolean('applies_to_subscriptions')->default(false)->after('per_user_limit');
            $table->json('subscription_plan_ids')->nullable()->after('applies_to_subscriptions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
