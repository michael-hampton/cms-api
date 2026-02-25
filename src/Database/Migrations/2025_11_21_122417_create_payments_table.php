<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreatePaymentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('site_id');
            $table->string('payment_method', 50); // stripe, paypal, bank_transfer, cash, etc.
            $table->string('payment_provider', 50)->nullable(); // stripe, paypal, etc.
            $table->string('transaction_id')->nullable(); // External transaction ID
            $table->string('payment_intent_id')->nullable(); // For Stripe payment intents
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->text('metadata')->nullable(); // JSON for additional payment data
            $table->text('error_message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->index(['order_id', 'status']);
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
