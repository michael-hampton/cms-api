<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class DropColumns extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('region_set_id');
            $table->dropColumn('territory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
