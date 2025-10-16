<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddUpdatedToProductViews extends Migration
{
    public function up(): void
    {
        Schema::table('product_views', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
