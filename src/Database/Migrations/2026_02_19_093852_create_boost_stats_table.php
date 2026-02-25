<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateBoostStatsTable extends Migration
{
    public function up(): void
    {
        Schema::create('boost_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boost_id')->unique();
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->decimal('spend_attributed', 10, 2)->default(0);
            $table->timestamp('last_aggregated_at')->nullable();
            $table->timestamps();

            $table->foreign('boost_id')->references('id')->on('boosts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
