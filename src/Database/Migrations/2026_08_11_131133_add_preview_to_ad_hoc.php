<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPreviewToAdHoc extends Migration
{
    public function up(): void
    {
        Schema::table('ad_hoc_fulfilment_requests', function (Blueprint $table) {
            $table->boolean('preview')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
