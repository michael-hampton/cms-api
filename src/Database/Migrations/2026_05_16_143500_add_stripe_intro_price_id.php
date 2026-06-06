<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStripeIntroPriceId extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plan_pricing', function (Blueprint $table) {
            $table->string('stripe_intro_price_id')->nullable()->after('stripe_price_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
