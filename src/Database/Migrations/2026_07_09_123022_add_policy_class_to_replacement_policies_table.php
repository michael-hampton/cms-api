<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPolicyClassToReplacementPoliciesTable extends Migration
{
    public function up(): void
    {
        Schema::table('replacement_policies', function (Blueprint $table) {
            $table->string('policy_class')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
