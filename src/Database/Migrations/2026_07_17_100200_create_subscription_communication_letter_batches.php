<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Dedicated print-fulfilment tables for payment communication letters.
 * Deliberately decoupled from PrintBatch/PrintFulfillment/IssueDelivery —
 * those model magazine issue mailings, this models ad-hoc correspondence.
 */
class CreateSubscriptionCommunicationLetterBatches extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_communication_letter_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_communication_letter_batches');
    }
}
