<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Reuses App\Enums\Member\CampaignPurpose (already governs campaign
 * consent checks) so subscription communications are classified the same
 * way campaigns are — MARKETING requires marketing_email consent,
 * TRANSACTIONAL/PRODUCT_UPDATES do not.
 */
class AddPurposeToSubscriptionCommunications extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_communications', function (Blueprint $table) {
            $table->string('purpose')->default('transactional')->after('channel_strategy');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_communications', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
}
