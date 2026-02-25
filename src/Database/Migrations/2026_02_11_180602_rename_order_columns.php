<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class RenameOrderColumns extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('offer_discount_cents');
            $table->dropColumn('reward_discount_cents');
            $table->dropColumn('voucher_discount_cents');

            $table->decimal('offer_discount', 10, 2)->default(0);
            $table->decimal('reward_discount', 10, 2)->default(0);
            $table->decimal('voucher_discount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
