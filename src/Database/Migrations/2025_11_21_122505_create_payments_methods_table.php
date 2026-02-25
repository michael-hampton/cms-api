<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreatePaymentsMethodsTable extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function ($table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('name', 100); // Stripe, PayPal, Bank Transfer, etc.
            $table->string('code', 50)->unique(); // stripe, paypal, bank_transfer
            $table->string('provider', 50)->nullable(); // stripe, paypal, null for manual
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_processing')->default(false); // true for online payments
            $table->text('configuration')->nullable(); // JSON for API keys, etc.
            $table->text('instructions')->nullable(); // Display to customer
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->index(['site_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
