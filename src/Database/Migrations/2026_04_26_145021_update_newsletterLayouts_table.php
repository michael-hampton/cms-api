<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdateNewsletterLayoutsTable extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_layouts', function (Blueprint $table) {
            $table->string('type', 32)
                ->default('newsletter')
                ->after('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
