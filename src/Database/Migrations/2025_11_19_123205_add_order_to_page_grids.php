<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddOrderToPageGrids extends Migration
{
    public function up(): void
    {
        Schema::table('page_grids', function (Blueprint $table) {
            $table->integer('order')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
