<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPremiumAccessToSubscriptionPlansTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('premium_access')
                ->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('premium_access')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
