<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionBundleTables extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_bundles', function (Blueprint $table) {
            $table->decimal('bundle_price', 10, 2)->comment('Authoritative discounted total');
            $table->decimal('total_price', 10, 2)->comment('Sum of constituent plan prices pre-discount');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
