<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddColumnsToSiteTable extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('subdomain')->nullable();
            $table->string('theme')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->boolean('is_default')->default(0);
            $table->json('settings')->nullable();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->index(['site_id', 'slug'], 'idx_site_slug');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('site_id');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->index('site_id');
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->index(['site_id', 'is_active'], 'idx_site_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
