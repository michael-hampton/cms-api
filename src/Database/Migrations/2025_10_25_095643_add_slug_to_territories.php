<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSlugToTerritories extends Migration
{
    public function up(): void
    {
        Schema::table('territories', function (Blueprint $table) {
            $table->string('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
