<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddColumnsToSubscriptionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('type', ['paid', 'trial'])
                ->default('paid')
                ->after('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
