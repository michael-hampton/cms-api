<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCategoryIdToTags extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->integer('category_id')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
