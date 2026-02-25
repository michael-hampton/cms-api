<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddEligibilityRulesToProductOffers extends Migration
{
    public function up(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->json('eligibility_rules')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->dropColumn('eligibility_rules');
        });
    }
}

return new AddEligibilityRulesToProductOffers();
