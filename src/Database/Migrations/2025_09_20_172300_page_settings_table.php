<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class PageSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->string('template')->default('default');
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();
            $table->string('redirect_url')->nullable();
            $table->integer('menu_order')->default(0);
            $table->string('parent_page')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->boolean('recurring')->default(false);
            $table->enum('recurring_period', ['daily', 'weekly', 'monthly', 'yearly'])->default('monthly');
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->unique('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
