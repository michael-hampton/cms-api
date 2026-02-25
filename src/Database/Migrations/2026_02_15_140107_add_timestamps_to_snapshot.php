<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddTimestampsToSnapshot extends Migration
{
    public function up(): void
    {
        Schema::table('cart_snapshots', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
