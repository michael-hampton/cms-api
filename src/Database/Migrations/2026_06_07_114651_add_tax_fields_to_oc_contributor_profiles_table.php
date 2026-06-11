<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddTaxFieldsToOcContributorProfilesTable extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table): void {
            $table->string('tax_classification', 50)->nullable()->after('stripe_customer_id');
            $table->string('vat_number', 100)->nullable()->after('tax_classification');

            $table->index('tax_classification', 'oc_contributor_profiles_tax_classification_idx');
        });
    }

    public function down(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'tax_classification',
                'vat_number',
            ]);
        });
    }
}
