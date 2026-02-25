<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddContentSnapshotToNewsletterSends extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_sends', function (Blueprint $table) {
            $table->json('content_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
