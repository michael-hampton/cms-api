<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Enums\OpenCollab\EarningsAdjustmentSource;
use App\Models\Model;
use App\Repositories\OpenCollab\EarningsLedgerRepository;

class EarningsAdjustmentService
{
    public function __construct(
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly AccrualTransitionService $accrualTransitionService,
        private readonly CreatorLiabilityService $creatorLiabilityService,
    ) {
    }

    public function reverse(
        int $ledgerEntryId,
        EarningsAdjustmentSource|string $source,
        string $reason,
        ?int $actorId = null,
    ): Model {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Adjustment reason is required.');
        }

        $sourceValue = $source instanceof EarningsAdjustmentSource
            ? $source->value
            : trim($source);

        if ($sourceValue === '') {
            throw new \InvalidArgumentException('Adjustment source is required.');
        }

        $entry = $this->ledgerRepository->find($ledgerEntryId);

        if (!$entry) {
            throw new \InvalidArgumentException("Earnings ledger entry [{$ledgerEntryId}] not found.");
        }

        $status = AccrualStatus::from($entry->accrual_status);

        return match ($status) {
            AccrualStatus::Estimated,
            AccrualStatus::Confirmed => $this->reverseUnsettled($entry, $reason, $actorId),

            AccrualStatus::Settled => $this->reverseSettled($entry, $reason, $actorId),

            AccrualStatus::Withdrawn => $this->reverseWithdrawn($entry, $sourceValue, $reason, $actorId),

            AccrualStatus::Reversed => throw new \InvalidArgumentException(
                "Earnings ledger entry [{$ledgerEntryId}] is already reversed."
            ),
        };
    }

    private function reverseUnsettled(Model $entry, string $reason, ?int $actorId): Model
    {
        return $this->accrualTransitionService->reverse(
            ledgerEntryId: (int) $entry->id,
            reason: $reason,
            actorId: $actorId,
        );
    }

    private function reverseSettled(
        Model $entry,
        string $reason,
        ?int $actorId,
    ): Model {
        // Match unsettled reversal: move the original out of Settled.
        // Posting a Settled counter-entry AND reversing the original would
        // double-debit available balance.
        return $this->accrualTransitionService->reverse(
            ledgerEntryId: (int) $entry->id,
            reason: $reason,
            actorId: $actorId,
        );
    }

    private function reverseWithdrawn(
        Model $entry,
        string $source,
        string $reason,
        ?int $actorId,
    ): Model {
        return $this->creatorLiabilityService->create(
            userId: (int) $entry->user_id,
            siteId: $this->resolveSiteId($entry),
            sourceType: $source,
            sourceId: (int) $entry->id,
            amount: abs((int) $entry->amount),
            currency: (string) $entry->currency,
            reason: $reason,
            createdBy: $actorId,
        );
    }

    private function resolveSiteId(Model $entry): int
    {
        if (!empty($entry->site_id)) {
            return (int) $entry->site_id;
        }

        throw new \InvalidArgumentException(
            "Cannot resolve site for earnings ledger entry [{$entry->id}]."
        );
    }
}