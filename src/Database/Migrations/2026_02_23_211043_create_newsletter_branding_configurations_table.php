<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterBrandingConfigurationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_branding_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newsletter_id')->unique();
            $table->string('logo_url')->nullable();
            $table->string('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->json('theme_json')->nullable();
            $table->text('custom_css')->nullable();
            $table->timestamps();

            $table->index('newsletter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
