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

            AccrualStatus::Settled => $this->reverseSettled($entry, $sourceValue, $reason, $actorId),

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
        string $source,
        string $reason,
        ?int $actorId,
    ): Model {
        $adjustment = $this->ledgerRepository->recordReversal(
            userId: (int) $entry->user_id,
            articleId: $entry->article_id ? (int) $entry->article_id : null,
            amount: abs((int) $entry->amount),
            currency: (string) $entry->currency,
            referenceId: $this->makeReferenceId($entry, $source),
            reason: $reason,
            sourceLedgerEntryId: (int) $entry->id,
        );

        $this->accrualTransitionService->reverse(
            ledgerEntryId: (int) $entry->id,
            reason: $reason,
            actorId: $actorId,
        );

        return $adjustment;
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

    private function makeReferenceId(Model $entry, string $source): string
    {
        return sprintf(
            '%s:%s:%s',
            $source,
            (int) $entry->id,
            date('YmdHis'),
        );
    }

    private function resolveSiteId(Model $entry): int
    {
        if (!empty($entry->site_id)) {
            return (int) $entry->site_id;
        }

        /**
         * Current ledger rows are article/page-based. If site_id is not directly
         * stored on the ledger row, this should be replaced with a PageRepository
         * lookup when you wire this into real production flows.
         */
        return 1;
    }
}