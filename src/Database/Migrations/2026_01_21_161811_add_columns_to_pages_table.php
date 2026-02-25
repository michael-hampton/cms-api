<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddColumnsToPagesTable extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('redirect_url')->nullable()->after('status');
            $table->dateTime('unpublished_at')->nullable()->after('redirect_url');
            $table->integer('unpublished_by')->nullable()->after('unpublished_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
