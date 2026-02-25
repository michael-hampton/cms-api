<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddActionToPageLikes extends Migration
{
    public function up(): void
    {
        Schema::table('page_likes', function ($table) {
            $table->enum('action', ['like', 'save'])->default('like')->after('site_id');
            $table->unique(['page_id', 'member_id', 'action']);
            $table->index(['member_id', 'site_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
