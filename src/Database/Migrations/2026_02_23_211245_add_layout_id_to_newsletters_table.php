<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddLayoutIdToNewslettersTable extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->unsignedBigInteger('layout_id')->nullable()->after('template');
            $table->index('layout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
