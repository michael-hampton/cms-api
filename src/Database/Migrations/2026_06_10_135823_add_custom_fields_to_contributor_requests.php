<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCustomFieldsToContributorRequests extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_requests', function (Blueprint $table): void {
            $table->json('custom_fields')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
