<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateImageTables extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->string('original_name', 255);
            $table->string('file_path', 500);
            $table->string('url', 500);
            $table->string('mime_type', 100);
            $table->integer('file_size');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->text('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('created_at');
            $table->index('is_active');
            $table->index('mime_type');
        });

        Schema::create('image_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
        });

        Schema::create('image_category_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id');
            $table->foreignId('category_id');
            $table->timestamps();

            $table->unique(['image_id', 'category_id'], 'unique_image_category');
            $table->foreign('image_id')->references('id')->on('images')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('image_categories')->cascadeOnDelete();


        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
