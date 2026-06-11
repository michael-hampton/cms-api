<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcPayoutLedgerEntriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_payout_ledger_entries', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('payout_id');
            $table->unsignedBigInteger('earnings_ledger_id');
            $table->integer('amount');

            $table->timestamps();

            $table->index('payout_id', 'oc_payout_ledger_entries_payout_id_idx');
            $table->unique('earnings_ledger_id', 'oc_payout_ledger_entries_ledger_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_payout_ledger_entries');
    }
}
