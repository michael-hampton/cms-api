<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdateOrderStatuses extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled', 'refunded', 'partially_refunded'])->default('pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
