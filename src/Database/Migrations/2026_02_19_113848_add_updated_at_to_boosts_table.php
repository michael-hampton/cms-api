<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddUpdatedAtToBoostsTable extends Migration
{
    public function up(): void
    {
        Schema::table('boosts', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
