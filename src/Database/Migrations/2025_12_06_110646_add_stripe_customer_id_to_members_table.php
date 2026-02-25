<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStripeCustomerIdToMembersTable extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
