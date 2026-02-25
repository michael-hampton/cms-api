<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateRefundsTables extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function ($table) {
            $table->id();
            $table->foreignId('order_id');
            $table->enum('refund_type', ['full', 'partial'])->default('full');
            $table->decimal('refund_amount', 10, 2);
            $table->string('reason');
            $table->text('internal_notes')->nullable();
            $table->boolean('notify_customer')->default(true);
            $table->boolean('restock_items')->default(true);
            $table->enum('status', ['pending', 'processed', 'failed', 'cancelled'])->default('pending');
            $table->foreignId('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('site_id');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('processed_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->index('order_id');
            $table->index('status');
            $table->index('site_id');
        });

        Schema::create('refund_items', function ($table) {
            $table->id();
            $table->foreignId('refund_id');
            $table->foreignId('order_item_id');
            $table->foreignId('product_id');
            $table->string('product_name');
            $table->integer('quantity');
            $table->integer('refund_quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('refund_amount', 10, 2);
            $table->timestamps();

            $table->foreign('refund_id')->references('id')->on('refunds')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->index('refund_id');
            $table->index('order_item_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
