<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAccountNumberToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('account_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
