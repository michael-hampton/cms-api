<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class TieredDiscountColumns extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tiered_discount', 10, 2)->default(0)->after('offer_discount');
            $table->decimal('merchant_funded', 10, 2)->default(0)->after('reward_discount');
            $table->decimal('platform_funded', 10, 2)->default(0)->after('merchant_funded');


        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
