<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePageAuthorsTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_authors', function($table) {
            $table->id();
            $table->foreignId('page_id');
            $table->foreignId('author_id');
            $table->enum('role', ['primary', 'contributor'])->default('primary');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('authors')->cascadeOnDelete();

            // Unique constraint to prevent duplicate author-page combinations
            $table->unique(['page_id', 'author_id', 'role']);
            $table->index(['page_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
