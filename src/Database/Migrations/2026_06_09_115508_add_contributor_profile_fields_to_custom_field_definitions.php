<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddContributorProfileFieldsToCustomFieldDefinitions extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->string('context')->default('page')->after('site_id');
            $table->string('storage_type')->default('custom_value')->after('context');
            $table->string('profile_column')->nullable()->after('storage_type');
            $table->boolean('is_locked')->default(false)->after('is_active');

            $table->index(['site_id', 'context']);
            $table->index(['site_id', 'context', 'is_active']);
            $table->index(['site_id', 'context', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
