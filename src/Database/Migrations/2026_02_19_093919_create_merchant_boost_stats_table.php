<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMerchantBoostStatsTable extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_boost_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->unique();
            $table->unsignedInteger('total_impressions')->default(0);
            $table->unsignedInteger('total_clicks')->default(0);
            $table->unsignedInteger('total_conversions')->default(0);
            $table->decimal('total_spend_attributed', 10, 2)->default(0);
            $table->timestamp('last_aggregated_at')->nullable();
            $table->timestamps();

            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
