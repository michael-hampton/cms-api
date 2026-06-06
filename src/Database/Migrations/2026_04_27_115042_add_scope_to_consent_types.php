<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddScopeToConsentTypes extends Migration
{
    public function up(): void
    {
        Schema::table('consent_types', function (Blueprint $table) {
            // 'member' = existing marketing/GDPR consents
            // 'contributor' = notification opt-in/out for contributors
            // 'system' = internal platform consents
            $table->string('scope', 20)->default('member')->after('category');
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
