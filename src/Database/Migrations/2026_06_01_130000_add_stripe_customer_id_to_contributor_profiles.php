<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStripeCustomerIdToContributorProfiles extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('payment_details');
        });
    }

    public function down(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
    }
}
