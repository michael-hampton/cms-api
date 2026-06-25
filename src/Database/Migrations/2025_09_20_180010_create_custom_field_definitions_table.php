<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCustomFieldDefinitionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->enum('type', ['text', 'textarea', 'number', 'url', 'email', 'boolean', 'date', 'select', 'multi_select', 'file', 'image'])->default('text');
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // For select/multi_select types
            $table->json('validation_rules')->nullable(); // Custom validation rules
            $table->string('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->string('group_name')->nullable(); // Group related fields
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('key');
            $table->index('type');
            $table->index('group_name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
