<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddBillingDatesToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function ($table) {
            $table->datetime('next_billing_date')->nullable()->after('end_date');
            $table->datetime('last_payment_date')->nullable()->after('next_billing_date');
            $table->index('next_billing_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
