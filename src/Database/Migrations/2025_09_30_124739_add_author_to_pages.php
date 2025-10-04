<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAuthorToPages extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable();

            $table->foreign('author_id')->references('id')->on('authors')->cascadeOnDelete();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
