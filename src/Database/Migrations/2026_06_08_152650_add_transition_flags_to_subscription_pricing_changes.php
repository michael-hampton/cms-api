<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddTransitionFlagsToSubscriptionPricingChanges extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_pricing_changes', function (Blueprint $table) {
            $table->boolean('requires_subscription_replacement')->default(false);
            $table->boolean('itd_required')->default(false);
            $table->string('itd_letter_code')->nullable();

            $table->index(
                ['requires_subscription_replacement', 'status'],
                'spc_replacement_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
