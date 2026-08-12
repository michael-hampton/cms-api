<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Events\OpenCollab\AccrualStatusChangedEvent;
use App\Exceptions\OpenCollab\InvalidAccrualTransitionException;
use App\Framework\Events\EventDispatcher;
use App\Models\EarningsLedger;
use App\Repositories\OpenCollab\EarningsLedgerRepository;

class AccrualTransitionService
{
    public function __construct(
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    public function confirm(int $ledgerEntryId, ?int $actorId = null): EarningsLedger
    {
        return $this->transition(
            ledgerEntryId: $ledgerEntryId,
            to: AccrualStatus::Confirmed,
            timestampColumn: 'confirmed_at',
            metadata: array_filter([
                'confirmed_by' => $actorId,
            ]),
        );
    }

    public function settle(int $ledgerEntryId, ?int $actorId = null): EarningsLedger
    {
        return $this->transition(
            ledgerEntryId: $ledgerEntryId,
            to: AccrualStatus::Settled,
            timestampColumn: 'settled_at',
            metadata: array_filter([
                'settled_by' => $actorId,
            ]),
        );
    }

    public function withdraw(int $ledgerEntryId, int $payoutId): EarningsLedger
    {
        return $this->transition(
            ledgerEntryId: $ledgerEntryId,
            to: AccrualStatus::Withdrawn,
            timestampColumn: 'withdrawn_at',
            metadata: [
                'payout_id' => $payoutId,
            ],
        );
    }

    public function reverse(int $ledgerEntryId, string $reason, ?int $actorId = null): EarningsLedger
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reversal reason is required.');
        }

        return $this->transition(
            ledgerEntryId: $ledgerEntryId,
            to: AccrualStatus::Reversed,
            timestampColumn: 'reversed_at',
            metadata: array_filter([
                'reversal_reason' => $reason,
                'reversed_by' => $actorId,
            ]),
        );
    }

    private function transition(
        int $ledgerEntryId,
        AccrualStatus $to,
        ?string $timestampColumn = null,
        array $metadata = [],
    ): EarningsLedger {
        $entry = $this->ledgerRepository->find($ledgerEntryId);

        if (!$entry) {
            throw new \InvalidArgumentException(
                "Earnings ledger entry [{$ledgerEntryId}] not found."
            );
        }

        $from = AccrualStatus::from($entry->accrual_status);

        /**
         * Idempotency guard.
         *
         * Useful for jobs/webhooks/retries where confirming an already confirmed
         * entry should not explode if the state is already correct.
         */
        if ($from === $to) {
            return $entry;
        }

        if (!$from->canTransitionTo($to)) {
            throw new InvalidAccrualTransitionException($ledgerEntryId, $from, $to);
        }

        if ($timestampColumn !== null) {
            $metadata[$timestampColumn] = now_datetime()->format('Y-m-d H:i:s');
        }

        $updated = $this->ledgerRepository->updateAccrualStatus(
            $ledgerEntryId,
            $to,
            $metadata,
        );

        $this->eventDispatcher->dispatch(
            new AccrualStatusChangedEvent(
                ledgerEntry: $updated,
                from: $from,
                to: $to,
            )
        );

        return $updated;
    }
}