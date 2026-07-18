<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionCommunicationLetterFulfilments extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_communication_letter_fulfilments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_communication_letter_batch_id');
            $table->unsignedBigInteger('subscription_communication_delivery_id');
            $table->unsignedBigInteger('subscription_id');
            $table->string('letter_code');
            $table->string('full_name');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('postcode');
            $table->string('country');
            $table->json('address_snapshot');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('subscription_communication_letter_batch_id')
                ->references('id')->on('subscription_communication_letter_batches')
                ->cascadeOnDelete();
            $table->foreign('subscription_communication_delivery_id')
                ->references('id')->on('subscription_communication_deliveries')
                ->cascadeOnDelete();
            $table->foreign('subscription_id')
                ->references('id')->on('subscriptions');

            // One letter per delivery — the delivery itself is already
            // deduped by SubscriptionCommunicationSender's dedupe_key check.
            $table->unique('subscription_communication_delivery_id', 'sclf_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_communication_letter_fulfilments');
    }
}
