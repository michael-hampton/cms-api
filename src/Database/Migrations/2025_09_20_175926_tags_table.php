<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class TagsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable(); // Hex color code
            $table->integer('usage_count')->default(0); // How many times used
            $table->boolean('is_featured')->default(false);
            $table->json('meta')->nullable(); // Additional metadata
            $table->timestamps();

            $table->index('slug');
            $table->index('usage_count');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
