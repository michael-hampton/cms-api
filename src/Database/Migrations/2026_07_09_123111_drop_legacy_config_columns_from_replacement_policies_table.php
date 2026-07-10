<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class DropLegacyConfigColumnsFromReplacementPoliciesTable extends Migration
{
    public function up(): void
    {
        Schema::table('replacement_policies', function (Blueprint $table) {
            $table->dropColumn([
                'allows_replacements',
                'allows_extensions',
                'max_replacements',
                'max_extensions',
                'require_stock',
                'requires_manager_approval',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
