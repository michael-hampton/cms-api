<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddExternalSourceToImages extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->string('external_provider', 50)->nullable();
            $table->string('external_id', 255)->nullable();
            $table->text('source_url')->nullable();
            $table->unique(
                ['site_id', 'external_provider', 'external_id'],
                'images_site_external_source_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropUnique('images_site_external_source_unique');
            $table->dropColumn('source_url');
            $table->dropColumn('external_id');
            $table->dropColumn('external_provider');
        });
    }
}
