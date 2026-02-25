<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCampaignIdToSubscribersTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->nullable()->after('newsletter_id');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('set null');
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
