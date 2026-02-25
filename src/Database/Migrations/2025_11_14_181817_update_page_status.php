<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdatePageStatus extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->enum('status', ['draft', 'published', 'archived', 'scheduled', 'waiting_approval', 'private', 'on_hold', 'internal'])->default('draft');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
