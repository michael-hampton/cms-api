<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddTrialToSubscriptionPlanPricing extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plan_pricing', function (Blueprint $table) {
            // Free trial in days — null means no trial on this tier
            $table->unsignedInteger('trial_days')->nullable()->after('digital_sale_price');

            // Introductory price in decimal (consistent with existing price/digital_price columns)
            // Must be lower than the standard price — enforced at validation layer
            $table->decimal('intro_price', 10, 2)->nullable()->after('trial_days');

            // How many billing cycles the intro price applies for
            $table->unsignedInteger('intro_cycles')->nullable()->after('intro_price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
