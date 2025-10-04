<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdatePageCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::table('page_categories', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreignId('category_id')->after('page_id');
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->index(['page_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
