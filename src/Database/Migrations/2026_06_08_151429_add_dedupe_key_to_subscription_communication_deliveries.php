<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDedupeKeyToSubscriptionCommunicationDeliveries extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_communication_deliveries', function (Blueprint $table) {
            $table->string('dedupe_key')->nullable()->after('token');
            $table->index('dedupe_key', 'scd_dedupe_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
