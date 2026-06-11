<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAccrualStatusToEarningsLedger extends Migration
{
    public function up(): void
    {
        Schema::table('oc_earnings_ledger', function ($table) {
            $table->string('accrual_status', 20)
                ->default('settled')
                ->after('earned_at');

            $table->timestamp('confirmed_at')->nullable()->after('accrual_status');
            $table->timestamp('settled_at')->nullable()->after('confirmed_at');
            $table->timestamp('withdrawn_at')->nullable()->after('settled_at');
            $table->timestamp('reversed_at')->nullable()->after('withdrawn_at');

            $table->index('accrual_status', 'idx_earnings_ledger_accrual_status');
            $table->index(['user_id', 'accrual_status'], 'idx_earnings_ledger_user_accrual');
        });

        // Migrate existing rows: all pre-existing entries are considered settled
        // (they represent real, payable earnings created before this system was introduced).
        \App\Framework\Database\Database::getInstance()->exec("UPDATE oc_earnings_ledger SET accrual_status = 'settled' WHERE accrual_status IS NULL OR accrual_status = ''"
        );
    }

    public function down(): void
    {
        Schema::table('oc_earnings_ledger', function (Blueprint $table) {
            $table->dropColumn([
                'accrual_status',
                'confirmed_at',
                'settled_at',
                'withdrawn_at',
                'reversed_at',
            ]);
        });
    }
}
