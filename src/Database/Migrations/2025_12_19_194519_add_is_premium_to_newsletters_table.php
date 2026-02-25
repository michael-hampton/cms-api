<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddIsPremiumToNewslettersTable extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false)->after('is_default');
            $table->index(['site_id', 'is_premium']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
