<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateEmailThemesTables extends Migration
{
    public function up(): void
    {
        Schema::create('email_themes', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('site_id')->nullable();
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->index(['site_id', 'is_active']);
            $table->index(['site_id', 'is_default']);
        });

        Schema::create('email_theme_colors', function ($table) {
            $table->id();
            $table->foreignId('theme_id');
            $table->string('color_key', 100);
            $table->string('color_value', 50);
            $table->timestamps();

            $table->foreign('theme_id')->references('id')->on('email_themes')->onDelete('cascade');

            $table->unique(['theme_id', 'color_key']);
        });

        Schema::create('email_theme_fonts', function ($table) {
            $table->id();
            $table->foreignId('theme_id');
            $table->string('font_key', 100);
            $table->string('font_family');
            $table->string('font_size', 50)->nullable();
            $table->string('font_weight', 50)->nullable();
            $table->timestamps();

            $table->foreign('theme_id')->references('id')->on('email_themes')->onDelete('cascade');

            $table->unique(['theme_id', 'font_key']);
        });

        Schema::create('email_theme_assets', function ($table) {
            $table->id();
            $table->foreignId('theme_id');
            $table->string('asset_key', 100);
            $table->string('asset_type', 50);
            $table->text('asset_url');
            $table->string('alt_text')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();

            $table->foreign('theme_id')->references('id')->on('email_themes')->onDelete('cascade');

            $table->unique(['theme_id', 'asset_key']);
        });

        Schema::create('email_theme_settings', function ($table) {
            $table->id();
            $table->foreignId('theme_id');
            $table->string('setting_key', 100);
            $table->text('setting_value');
            $table->string('setting_type', 50)->default('string');
            $table->timestamps();

            $table->foreign('theme_id')->references('id')->on('email_themes')->onDelete('cascade');

            $table->unique(['theme_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
