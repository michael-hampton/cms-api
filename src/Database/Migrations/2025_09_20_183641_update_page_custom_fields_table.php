<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdatePageCustomFieldsTable extends Migration
{
    public function up(): void
    {
        Schema::table('page_custom_fields', function (Blueprint $table) {
            $table->dropColumn(['field_key', 'field_type']);
            $table->foreignId('custom_field_definition_id')->after('page_id');
            $table->foreign('custom_field_definition_id')->references('id')->on('custom_field_definitions')->cascadeOnDelete();
            $table->index(['page_id', 'custom_field_definition_id'], 'page_custom_field_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
