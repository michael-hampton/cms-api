<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddUnsubscribedAtToSubscribers extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dateTime('unsubscribed_at')->nullable();
            $table->index(['site_id', 'unsubscribed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
