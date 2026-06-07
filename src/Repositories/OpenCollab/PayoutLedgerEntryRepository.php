<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\PayoutLedgerEntry;
use App\Repositories\Repository;

class PayoutLedgerEntryRepository extends Repository
{
    public function attach(
        int $payoutId,
        int $ledgerEntryId,
        int $amount,
    ): Model {
        return $this->create([
            'payout_id' => $payoutId,
            'earnings_ledger_id' => $ledgerEntryId,
            'amount' => $amount,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function forPayout(int $payoutId): Collection
    {
        return PayoutLedgerEntry::where('payout_id', $payoutId)
            ->orderBy('id')
            ->get();
    }

    public function existsForLedgerEntry(int $ledgerEntryId): bool
    {
        return PayoutLedgerEntry::where('earnings_ledger_id', $ledgerEntryId)->exists();
    }

    protected function getModelClass(): string
    {
        return PayoutLedgerEntry::class;
    }
}