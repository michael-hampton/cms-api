<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddLogoIdToSites extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function ($table) {
            $table->unsignedBigInteger('logo_image_id')->nullable()->after('logo');

            $table->foreign('logo_image_id')
                ->references('id')
                ->on('images')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
