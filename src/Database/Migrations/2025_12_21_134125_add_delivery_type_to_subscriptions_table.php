<?php

use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDeliveryTypeToSubscriptionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('delivery_type', [SubscriptionType::DIGITAL->value, SubscriptionType::PRINTED->value])->nullable();
            $table->string('download_url')->nullable();
            $table->dateTime('download_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
