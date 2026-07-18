<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Suppression/consent-adjacent flags checked by CommunicationConsentGate
 * before any subscription communication is created. These are distinct
 * from the marketing consent system (consent_types/member_consents) —
 * they're hard exclusions/notes rather than opt-in preferences.
 */
class AddConsentFlagsToMembers extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('is_deceased')->default(false)->after('is_active');
            $table->boolean('do_not_mail')->default(false)->after('is_deceased');
            $table->boolean('is_minor')->default(false)->after('do_not_mail');
            $table->string('authorized_third_party_name')->nullable()->after('is_minor');
            $table->string('authorized_third_party_relationship')->nullable()->after('authorized_third_party_name');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('is_deceased');
            $table->dropColumn('do_not_mail');
            $table->dropColumn('is_minor');
            $table->dropColumn('authorized_third_party_name');
            $table->dropColumn('authorized_third_party_relationship');
        });
    }
}
