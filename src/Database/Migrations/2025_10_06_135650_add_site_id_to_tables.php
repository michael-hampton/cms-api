<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSiteIdToTables extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->string('currency')->nullable();
            $table->string('currency_symbol')->nullable();
            $table->string('currency_position')->nullable();
            $table->string('date_format')->nullable();
            $table->string('time_format')->nullable();
        });

        Schema::table('images', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('image_categories', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('authors', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
