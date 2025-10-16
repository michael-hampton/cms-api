<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateRegionsetTables extends Migration
{
    public function up(): void
    {
        Schema::create('region_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('site_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->unique(['slug', 'site_id'], 'unique_slug_site');
            $table->index(['site_id', 'is_active'], 'idx_site_active');
            $table->index('sort_order', 'idx_sort_order');
        });

        Schema::create('territories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10);
            $table->foreignId('region_set_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('site_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('region_set_id')->references('id')->on('region_sets')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->unique(['code', 'site_id'], 'unique_code_site');
            $table->index('region_set_id', 'idx_region_set');
            $table->index(['site_id', 'is_active'], 'idx_site_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
