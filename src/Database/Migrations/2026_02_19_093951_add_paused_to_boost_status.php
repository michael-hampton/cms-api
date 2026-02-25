<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPausedToBoostStatus extends Migration
{
    public function up(): void
    {
        Schema::table('boosts', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->enum('status', ['pending', 'active', 'paused', 'expired', 'cancelled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
