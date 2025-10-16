<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CustomFieldTypes extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->enum('type', ['text', 'textarea', 'number', 'url', 'email', 'boolean', 'date', 'select', 'multi_select', 'file', 'json'])->default('text');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
