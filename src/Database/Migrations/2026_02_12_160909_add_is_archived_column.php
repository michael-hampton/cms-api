<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddIsArchivedColumn extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
