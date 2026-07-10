<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddLimitScopeToReplacementPoliciesTable extends Migration
{
    public function up(): void
    {
        Schema::table('replacement_policies', function (Blueprint $table) {
            $table->string('replacement_limit_scope', 20)->default('per_subscription')->after('max_replacements');
            $table->string('extension_limit_scope', 20)->default('per_subscription')->after('max_extensions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
