<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdateBoostStatsTable extends Migration
{
    public function up(): void
    {
        Schema::table('boost_stats', function (Blueprint $table) {
            $table->integer('boost_score')->default(0)->after('spend_attributed');
            $table->float('rank_score')->default(0)->after('boost_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
