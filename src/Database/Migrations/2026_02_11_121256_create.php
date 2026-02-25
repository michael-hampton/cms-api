<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class Create extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->string('type', 50); // voucher_funding, refund, adjustment, etc.
            $table->decimal('amount', 10, 2); // Positive for credits, negative for debits
            $table->decimal('balance_before', 10, 2)->default(0);
            $table->decimal('balance_after', 10, 2);
            $table->string('status', 20)->default('completed'); // completed, pending_review, failed
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable(); // For admin notes on pending_review transactions
            $table->timestamps();

            // Foreign keys
            $table->foreign('merchant_id')
                ->references('id')
                ->on('merchants')
                ->onDelete('cascade');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');

            $table->foreign('voucher_id')
                ->references('id')
                ->on('vouchers')
                ->onDelete('set null');

            // Indexes
            $table->index(['merchant_id', 'created_at']);
            $table->index(['merchant_id', 'type']);
            $table->index(['merchant_id', 'status']);
            $table->index(['order_id']);
            $table->index(['voucher_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
