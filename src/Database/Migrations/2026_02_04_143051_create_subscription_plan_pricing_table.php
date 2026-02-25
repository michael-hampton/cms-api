<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionPlanPricingTable extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id');
            $table->integer('duration_months'); // 6, 12, 24, etc.
            $table->integer('issue_count'); // How many issues they get
            $table->decimal('price', 10, 2);
            $table->decimal('original_price', 10, 2)->nullable(); // For showing strikethrough
            $table->integer('discount_percentage')->nullable(); // e.g., 37, 44
            $table->string('label'); // "6 month subscription", "1 year subscription"
            $table->string('period_description'); // "for 6 issues", "for one year / 12 issues"
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();

            $table->unique(['plan_id', 'duration_months']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
