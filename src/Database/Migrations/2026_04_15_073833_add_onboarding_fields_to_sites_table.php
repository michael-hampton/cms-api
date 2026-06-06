<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddOnboardingFieldsToSitesTable extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('require_payment_setup')->default(true)->after('id');
            $table->boolean('require_contracts')->default(true)->after('require_payment_setup');
            $table->boolean('require_guidelines_ack')->default(true)->after('require_contracts');
            $table->unsignedInteger('guidelines_version')->default(1)->after('require_guidelines_ack');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
