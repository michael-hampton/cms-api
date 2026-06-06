<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFieldsToNewsletterLayouts extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_layouts', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('category')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
