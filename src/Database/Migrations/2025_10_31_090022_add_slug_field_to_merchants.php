<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSlugFieldToMerchants extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('slug')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
