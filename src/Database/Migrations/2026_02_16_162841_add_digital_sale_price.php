<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDigitalSalePrice extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plan_pricing', function (Blueprint $table) {
            $table->decimal('digital_sale_price', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
