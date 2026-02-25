<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddVoucherSupportToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function ($table) {
            $table->foreignId('voucher_id')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('original_price', 10, 2)->nullable();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
