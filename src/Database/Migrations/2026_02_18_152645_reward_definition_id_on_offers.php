<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class RewardDefinitionIdOnOffers extends Migration
{
    public function up(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->foreignId('reward_definition_id')->nullable();

            $table->foreign('reward_definition_id')->references('id')->on('reward_definitions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
