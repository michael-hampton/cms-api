<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddContributorAgeVerification extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_profiles', function (Blueprint $table) {
            // Personal data — store only what is needed for compliance.
            // Never log raw values; reference the column by name in structured logs.
            $table->date('date_of_birth')->nullable()->after('bio');

            $table->timestamp('age_verified_at')->nullable()->after('date_of_birth');

            // Enum values: self_declared | kyc_verified | manual_review
            // NULL means not yet verified.
            $table->string('age_verification_method', 32)->nullable()->after('age_verified_at');

            // Convenience boolean snapshot — NOT authoritative for policy decisions.
            // Runtime checks must recalculate from DOB + site minimum age.
            $table->boolean('minimum_age_confirmed')->default(false)->after('age_verification_method');
        });

        Schema::table('sites', function (Blueprint $table) {
            // Default 18; minimum allowed value enforced at application layer.
            $table->unsignedTinyInteger('minimum_contributor_age')->default(18)->after('require_guidelines_ack');
            $table->boolean('require_age_verification')->default(true)->after('minimum_contributor_age');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
