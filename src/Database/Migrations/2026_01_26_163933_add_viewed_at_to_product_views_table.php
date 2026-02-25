<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddViewedAtToProductViewsTable extends Migration
{
    public function up(): void
    {
        Schema::table('product_views', function (Blueprint $table) {
            $table->dateTime('viewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
