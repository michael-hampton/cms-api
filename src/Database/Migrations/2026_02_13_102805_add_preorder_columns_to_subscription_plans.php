<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPreorderColumnsToSubscriptionPlans extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dateTime('release_date')->nullable();
            $table->boolean('pre_release_enabled')->default(false);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dateTime('access_starts_at')->nullable();
            $table->dateTime('first_shipment_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
