<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAutoGenerateColumn extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_relations', function (Blueprint $table) {
            $table->boolean('is_auto_generated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
