<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateBriefsTables extends Migration
{
    public function up(): void
    {
        Schema::create('briefs', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('owner_id');
            $table->foreignId('category_id')->nullable();
            $table->foreignId('site_id');
            $table->enum('status', ['active', 'converted', 'archived'])->default('active');
            $table->foreignId('converted_page_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('converted_page_id')->references('id')->on('pages')->onDelete('set null');
        });

        Schema::create('brief_attachments', function ($table) {
            $table->id();
            $table->foreignId('brief_id');
            $table->enum('type', ['image', 'product']);
            $table->foreignId('image_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->string('url')->nullable(); // For product links
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('brief_id')->references('id')->on('briefs')->onDelete('cascade');
            $table->foreign('image_id')->references('id')->on('images')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });

        Schema::create('brief_comments', function ($table) {
            $table->id();
            $table->foreignId('brief_id');
            $table->foreignId('user_id');
            $table->foreignId('parent_comment_id')->nullable();
            $table->text('content');
            $table->text('highlighted_text')->nullable();
            $table->json('highlighted_range')->nullable();
            $table->timestamps();

            $table->foreign('brief_id')->references('id')->on('briefs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_comment_id')->references('id')->on('brief_comments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
