<?php

namespace App\Services\OpenCollab;

use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutLedgerEntryRepository;

class PayoutLedgerService
{
    public function __construct(
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly PayoutLedgerEntryRepository $payoutLedgerEntryRepository,
        private readonly AccrualTransitionService $accrualTransitionService,
    ) {
    }

    public function attachSettledEntriesToPayout(
        int $payoutId,
        int $userId,
        int $amountToAttach,
    ): int {
        if ($amountToAttach <= 0) {
            return 0;
        }

        $remaining = $amountToAttach;
        $attached = 0;

        $entries = $this->ledgerRepository->settledAvailableForPayout($userId);

        foreach ($entries as $entry) {
            if ($remaining <= 0) {
                break;
            }

            $entryAmount = (int) $entry->amount;

            if ($entryAmount <= 0) {
                continue;
            }

            $attachAmount = min($remaining, $entryAmount);

            if ($attachAmount !== $entryAmount) {
                throw new \RuntimeException(
                    'Partial payout ledger attachment is not supported yet.'
                );
            }

            $this->payoutLedgerEntryRepository->attach(
                payoutId: $payoutId,
                ledgerEntryId: (int) $entry->id,
                amount: $attachAmount,
            );

            $remaining -= $attachAmount;
            $attached += $attachAmount;
        }

        if ($attached !== $amountToAttach) {
            throw new \RuntimeException(
                'Unable to attach enough settled ledger entries to cover payout amount.'
            );
        }

        return $attached;
    }

    public function markPayoutLedgerEntriesWithdrawn(int $payoutId): void
    {
        $entries = $this->payoutLedgerEntryRepository->forPayout($payoutId);

        foreach ($entries as $entry) {
            $this->accrualTransitionService->withdraw(
                ledgerEntryId: (int) $entry->earnings_ledger_id,
                payoutId: $payoutId,
            );
        }
    }
}