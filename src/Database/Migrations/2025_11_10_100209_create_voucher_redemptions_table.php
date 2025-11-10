<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateVoucherRedemptionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('discount_amount', 10, 2);
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->foreign('voucher_id')
                ->references('id')
                ->on('vouchers')
                ->onDelete('cascade');

            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->onDelete('set null');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');

            $table->index(['voucher_id', 'member_id']);
            $table->index('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
