<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFieldsToCampaigns extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('campaign_type')->nullable();
            $table->string('status')->nullable();
            $table->integer('campaign_id')->nullable();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
