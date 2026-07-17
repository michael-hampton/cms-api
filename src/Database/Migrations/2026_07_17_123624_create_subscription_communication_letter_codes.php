<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Canonical registry of letter codes used by letter-channel subscription
 * communications. One communication maps to exactly one letter code today;
 * kept as its own table (rather than a column on subscription_communications)
 * so the letter-code catalogue can be managed/audited independently of the
 * communication's content/scheduling definition.
 */
class CreateSubscriptionCommunicationLetterCodes extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_communication_letter_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_communication_id');
            $table->string('letter_code')->unique();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign('subscription_communication_id', 'sclc_communication_fk')
                ->references('id')->on('subscription_communications')
                ->cascadeOnDelete();
            $table->unique('subscription_communication_id', 'sclc_communication_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_communication_letter_codes');
    }
}