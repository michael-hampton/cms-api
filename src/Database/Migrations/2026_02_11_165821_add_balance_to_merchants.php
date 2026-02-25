<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddBalanceToMerchants extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
