<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddChannelStrategyToSubscriptionCommunications extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_communications', function (Blueprint $table) {
            $table->string('channel_strategy')->default('all')->after('channels');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_communications', function (Blueprint $table) {
            $table->dropColumn('channel_strategy');
        });
    }
}
