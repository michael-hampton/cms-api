<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStripeColumnsToPayments extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->unique()->after('payment_intent_id');
            $table->string('hosted_invoice_url')->nullable()->after('stripe_invoice_id');
            $table->json('raw_payload')->nullable()->after('hosted_invoice_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
