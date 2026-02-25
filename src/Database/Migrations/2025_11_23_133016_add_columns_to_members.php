<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddColumnsToMembers extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->integer('total_points')->default(0);
            $table->json('activity_stats');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
