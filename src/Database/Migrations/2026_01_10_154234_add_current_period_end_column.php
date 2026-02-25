<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddCurrentPeriodEndColumn extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function ($table) {
            $table->timestamp('current_period_start')->nullable()->after('next_billing_date');
            $table->timestamp('current_period_end')->nullable()->after('current_period_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
