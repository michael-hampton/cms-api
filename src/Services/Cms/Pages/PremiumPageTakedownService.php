<?php

namespace App\Services\Cms\Pages;

use App\Enums\OpenCollab\AccrualStatus;
use App\Events\OpenCollab\PremiumMonetisationDisabledEvent;
use App\Framework\Events\EventDispatcher;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageMetadataRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;

class PremiumPageTakedownService
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PageMetadataRepository $metadataRepository,
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly PageHistoryService $historyService,
        private readonly EventDispatcher              $eventDispatcher,
    ) {
    }

    public function disableMonetisation(Page $page, int $actorId, string $reason): Page
    {
        if ($page->isMonetisationDisabled()) {
            return $page;
        }

        $now = date('Y-m-d H:i:s');

        $this->pageRepository->update($page->id, [
            'is_paid' => false,
            'monetisation_disabled_at' => $now,
            'monetisation_disabled_by' => $actorId,
            'monetisation_disabled_reason' => $reason,
        ]);

        $this->metadataRepository->createOrUpdate($page->id, [
            'visibility' => 'public',
        ]);

        $reversalSummary = $this->reverseActiveEarnings($page, $actorId, $reason);

        $this->historyService->logPageAction(
            pageId: $page->id,
            action: 'premium_disabled',
            description: 'Premium monetisation disabled',
            changes: [
                'is_paid' => ['old' => (bool) $page->is_paid, 'new' => false],
                'visibility' => ['old' => $page->metadata->visibility ?? null, 'new' => 'public'],
                'disabled_by' => $actorId,
                'reason' => $reason,
                'reversal_summary' => $reversalSummary,
            ],
            includeSnapshot: true
        );

        $this->eventDispatcher->dispatch(
            new PremiumMonetisationDisabledEvent($page, $actorId, $reason)
        );

        return $this->pageRepository->find($page->id);
    }

    /**
     * @return array{reversed: int, withdrawn_flagged: int, ignored: int}
     */
    private function reverseActiveEarnings(Page $page, int $actorId, string $reason): array
    {
        $summary = [
            'reversed' => 0,
            'withdrawn_flagged' => 0,
            'ignored' => 0,
        ];

        foreach ($this->ledgerRepository->forArticle((int) $page->id) as $entry) {
            $status = AccrualStatus::from($entry->accrual_status);

            if (in_array($status, AccrualStatus::active(), true)) {
                $this->ledgerRepository->reverse(
                    (int) $entry->id,
                    'premium_takedown:' . $reason,
                    $actorId
                );

                $summary['reversed']++;
                continue;
            }

            if ($status === AccrualStatus::Withdrawn) {
                // No payout mutation here. That is manual finance review / liability later.
                $summary['withdrawn_flagged']++;
                continue;
            }

            $summary['ignored']++;
        }

        return $summary;
    }
}