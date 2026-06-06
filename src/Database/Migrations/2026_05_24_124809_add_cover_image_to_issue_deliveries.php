<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCoverImageToIssueDeliveries extends Migration
{
    public function up(): void
    {
        Schema::table('issue_deliveries', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('issue_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
