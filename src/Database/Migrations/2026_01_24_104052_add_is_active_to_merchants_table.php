<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddIsActiveToMerchantsTable extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            // Adding is_active with a default of true
            $table->boolean('is_active')->default(true)->after('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
