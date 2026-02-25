<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdatePageMetadataVisibility extends Migration
{
    public function up(): void
    {
        Schema::table('page_metadata', function (Blueprint $table) {
            $table->dropColumn('visibility');
            $table->enum('visibility', ['free', 'member', 'premium']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
