<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class FreeGiftChanges extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_gift')->default(false)->after('quantity');
            $table->json('gift_metadata')->nullable()->after('is_gift');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
