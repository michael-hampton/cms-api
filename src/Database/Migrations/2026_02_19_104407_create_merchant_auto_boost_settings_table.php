<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMerchantAutoBoostSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_auto_boost_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->unique();
            $table->decimal('monthly_budget', 10, 2);
            $table->string('goal');               // maximise_revenue | promote_deals | clear_inventory
            $table->json('contexts_allowed');     // ["listing","deals","recommendations"]
            $table->boolean('is_enabled')->default(false);
            $table->decimal('budget_used_this_month', 10, 2)->default(0);
            $table->string('budget_period_month')->nullable(); // e.g. "2026-01"
            $table->timestamps();

            $table->index('merchant_id');
            $table->index(['is_enabled', 'merchant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
