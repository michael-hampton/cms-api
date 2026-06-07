<?php

namespace App\Jobs\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Jobs\BaseJob;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Services\OpenCollab\AccrualTransitionService;

class SettleLedgerEntryJob extends BaseJob
{
    private AccrualTransitionService $accrualTransitionService;
    private EarningsLedgerRepository $ledgerRepository;

    public function __construct(
        private readonly int $ledgerEntryId,
    )
    {
    }

    public function handle(): void
    {
        $entry = $this->ledgerRepository->find($this->ledgerEntryId);

        if (!$entry) {
            return;
        }

        // Already settled or withdrawn — idempotent, nothing to do
        if (in_array($entry->accrual_status, [
            AccrualStatus::Settled->value,
            AccrualStatus::Withdrawn->value,
        ], true)) {
            return;
        }

        // Must be confirmed before settling
        if ($entry->accrual_status === AccrualStatus::Estimated->value) {
            $this->accrualTransitionService->confirm($this->ledgerEntryId);
        }

        $this->accrualTransitionService->settle($this->ledgerEntryId);
    }
}