<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdateTypesOnSubscriptionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->enum('type', ['free', 'premium', 'paid', 'trial']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
