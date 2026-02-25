<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddRecipientsToNewsletterSends extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_sends', function (Blueprint $table) {
            $table->json('recipients')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
