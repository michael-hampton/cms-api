<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAccrualAuditFieldsToEarningsLedger extends Migration
{
    public function up(): void
    {
        Schema::table('oc_earnings_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('confirmed_by')->nullable()->after('confirmed_at');
            $table->unsignedBigInteger('settled_by')->nullable()->after('settled_at');

            $table->unsignedBigInteger('payout_id')->nullable()->after('withdrawn_at');

            $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_at');
            $table->text('reversal_reason')->nullable()->after('reversed_by');
        });
    }

    public function down(): void
    {
        Schema::table('oc_earnings_ledger', function (Blueprint $table) {
            $table->dropColumn([
                'confirmed_by',
                'settled_by',
                'payout_id',
                'reversed_by',
                'reversal_reason',
            ]);
        });
    }
}
