<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterBrandingVersionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_branding_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branding_config_id');
            $table->unsignedInteger('version_number');
            $table->json('branding_json_snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['branding_config_id', 'version_number']);
            $table->index('branding_config_id');

            $table->foreign('branding_config_id')
                ->references('id')
                ->on('newsletter_branding_configurations')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
