<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDispatchedDateToIssueDeliveries extends Migration
{
    public function up(): void
    {
        Schema::table('issue_deliveries', function (Blueprint $table) {
            $table->text('dispatch_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
