<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class PageTagsTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->string('tag');
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index(['page_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::drop('page_tags');
    }
}
