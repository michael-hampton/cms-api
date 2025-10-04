<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMenusTable extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('layout_config')->nullable(); // For different layout types
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id');
            $table->foreignId('parent_id')->nullable();
            $table->string('label');
            $table->enum('target_type', ['page', 'category', 'custom', 'external']);
            $table->string('target_id')->nullable(); // For page/category IDs
            $table->string('custom_url')->nullable(); // For custom/external URLs
            $table->string('css_class')->nullable();
            $table->string('icon')->nullable();
            $table->json('attributes')->nullable(); // For additional HTML attributes
            $table->boolean('open_in_new_tab')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
            $table->index(['target_type', 'target_id']);
            $table->foreign('menu_id')->references('id')->on('menus')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('menu_items')->cascadeOnDelete();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
