<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class PageMetadataTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->string('content_type')->nullable();
            $table->string('block_category')->nullable();
            $table->string('author')->nullable();
            $table->datetime('publish_date')->nullable();
            $table->datetime('expiry_date')->nullable();
            $table->enum('visibility', ['public', 'private', 'password'])->default('public');
            $table->string('password')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->boolean('is_reusable_block')->default(false);
            $table->string('block_preview_image')->nullable();
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
