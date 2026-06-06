<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddProviderMetadataToPayouts extends Migration
{
    public function up(): void
    {
        Schema::table('oc_payouts', function (Blueprint $table) {
            $table->string('provider')->nullable();
            $table->string('provider_payout_id')->nullable();
            $table->string('provider_transfer_id')->nullable();
            $table->string('provider_status')->nullable();
            $table->json('provider_response_json')->nullable();
            $table->integer('processing_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('oc_payouts', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'provider_payout_id',
                'provider_transfer_id',
                'provider_status',
                'provider_response_json',
                'processing_attempts',
            ]);
        });
    }
}

