<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddNameToImages extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->string('name', 255)->nullable();
            $table->index('name', 'idx_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
