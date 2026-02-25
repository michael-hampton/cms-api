<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddIsPreviewToNewsletterSends extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_sends', function (Blueprint $table) {
            $table->boolean('is_preview')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
