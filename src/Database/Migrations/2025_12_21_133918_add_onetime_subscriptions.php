<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddOnetimeSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->enum('plan_type', ['recurring', 'onetime'])->default('recurring');
            $table->string('digital_download_url')->nullable();
            $table->boolean('print_shipping_required')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
