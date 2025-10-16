<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateImageTagsTable extends Migration
{
    public function up(): void
    {
        Schema::create('image_tag', function (Blueprint $table) {
            $table->foreignId('image_id');
            $table->foreignId('tag_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->primary(['image_id', 'tag_id']);
            $table->foreign('image_id')->references('id')->on('images')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');

            $table->index('tag_id', 'idx_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
