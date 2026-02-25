<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSoftDeletes extends Migration
{
    public function up(): void
    {
        Schema::table('reward_definitions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('member_rewards', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
