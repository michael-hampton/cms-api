<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class PageCustomFieldsTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->enum('field_type', ['text', 'number', 'url', 'email', 'boolean', 'date', 'json'])->default('text');
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index(['page_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::drop('page_custom_fields');
    }
}
