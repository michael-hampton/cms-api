<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDeliveryPauseToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('delivery_paused')->default(false);
            $table->dateTime('delivery_pause_start')->nullable();
            $table->dateTime('delivery_pause_end')->nullable();
            $table->string('delivery_pause_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
