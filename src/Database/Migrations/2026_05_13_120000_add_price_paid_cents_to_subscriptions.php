<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPricePaidCentsToSubscriptions extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('subscriptions', 'price_paid_cents')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('price_paid_cents')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
