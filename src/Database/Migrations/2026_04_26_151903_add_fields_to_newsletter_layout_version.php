<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFieldsToNewsletterLayoutVersion extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_layout_versions', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
