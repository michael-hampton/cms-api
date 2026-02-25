<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateBoostLimitsTable extends Migration
{
    public function up(): void
    {
        Schema::create('boost_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boost_id')->unique(); // one limit set per boost
            $table->unsignedInteger('max_impressions')->nullable();
            $table->unsignedInteger('max_clicks')->nullable();
            $table->decimal('max_spend', 10, 2)->nullable();
            $table->boolean('pause_on_breach')->default(true);
            $table->timestamps();

            $table->foreign('boost_id')->references('id')->on('boosts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
