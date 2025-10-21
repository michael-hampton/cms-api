<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateProductVoucherTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_voucher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('voucher_id');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();

            $table->unique(['product_id', 'voucher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
