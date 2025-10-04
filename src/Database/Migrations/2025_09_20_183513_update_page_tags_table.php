<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdatePageTagsTable extends Migration
{
    public function up(): void
    {
        Schema::table('page_tags', function (Blueprint $table) {
            $table->dropColumn('tag');
            $table->foreignId('tag_id')->after('page_id');
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
            $table->index(['page_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
