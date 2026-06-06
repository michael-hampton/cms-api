<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFieldsToNewsletterLayouts2 extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_layouts', function (Blueprint $table) {
            $table->boolean('use_default_theme')->default(true);
            $table->foreignId('theme_id')->nullable();
            $table->foreign('theme_id')->on('email_themes')->references('id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
