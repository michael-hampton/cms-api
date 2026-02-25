<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddZonesToPages extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('zones')->nullable()->after('gallery_slides');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
