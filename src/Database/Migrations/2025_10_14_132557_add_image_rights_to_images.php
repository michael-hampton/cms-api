<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddImageRightsToImages extends Migration
{
    public function up(): void
    {
        Schema::table('images', function ($table) {
            $table->string('image_rights', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
