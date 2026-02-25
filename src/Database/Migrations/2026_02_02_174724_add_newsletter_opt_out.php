<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddNewsletterOptOut extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscription_preferences', function (Blueprint $table) {
            $table->boolean('newsletter_opt_out')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
