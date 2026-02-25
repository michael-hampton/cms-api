<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAppliesToColumnToVouchers extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->enum('applies_to', [
                'one_time',
                'subscription_first_cycle',
                'subscription_recurring',
                'both'
            ])->default('one_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
