<?php

namespace App\Services\Cms\Pages;

use App\Events\OpenCollab\PremiumMonetisationApprovedEvent;
use App\Events\OpenCollab\PremiumMonetisationRejectedEvent;
use App\Framework\Events\EventDispatcher;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageMetadataRepository;
use App\Repositories\Cms\Pages\PageRepository;

class PremiumPageApprovalService
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PageMetadataRepository $metadataRepository,
        private readonly PremiumPageEligibilityService $eligibilityService,
        private readonly PageHistoryService $historyService,
        private readonly EventDispatcher              $eventDispatcher,
    ) {
    }

    public function approvePremium(
        Page $page,
        int $editorId,
        int $approvedPrice,
        ?string $note = null,
    ): Page {
        if ($approvedPrice <= 0) {
            throw new \InvalidArgumentException('Approved price must be greater than zero.');
        }

        $this->eligibilityService->assertEligible($page, $approvedPrice);

        $now = date('Y-m-d H:i:s');

        $this->pageRepository->update($page->id, [
            'is_paid' => true,
            'price' => $approvedPrice,
            'premium_approved_at' => $now,
            'premium_approved_by' => $editorId,
            'premium_approval_note' => $note,

            'premium_rejected_at' => null,
            'premium_rejected_by' => null,
            'premium_rejection_reason' => null,

            'monetisation_disabled_at' => null,
            'monetisation_disabled_by' => null,
            'monetisation_disabled_reason' => null,
        ]);

        $this->metadataRepository->createOrUpdate($page->id, [
            'visibility' => 'premium',
        ]);

        $updated = $this->pageRepository->find($page->id);

        if (!$updated) {
            throw new \RuntimeException("Updated page [{$page->id}] could not be loaded.");
        }

        $this->historyService->logPageAction(
            pageId: $page->id,
            action: 'premium_approved',
            description: 'Premium monetisation approved',
            changes: [
                'is_paid' => ['old' => (bool) $page->is_paid, 'new' => true],
                'price' => ['old' => $page->price, 'new' => $approvedPrice],
                'visibility' => ['old' => $page->metadata->visibility ?? null, 'new' => 'premium'],
                'premium_approved_by' => $editorId,
                'premium_approval_note' => $note,
            ],
            includeSnapshot: true
        );

        $this->eventDispatcher->dispatch(
            new PremiumMonetisationApprovedEvent($updated, $editorId)
        );

        return $updated;
    }

    public function approveFree(Page $page, int $editorId, ?string $note = null): Page
    {
        $this->pageRepository->update($page->id, [
            'is_paid' => false,
            'price' => null,
            'premium_approved_at' => null,
            'premium_approved_by' => null,
            'premium_approval_note' => null,
        ]);

        $this->metadataRepository->createOrUpdate($page->id, [
            'visibility' => 'free',
        ]);

        $updated = $this->pageRepository->find($page->id);

        $this->historyService->logPageAction(
            pageId: $page->id,
            action: 'premium_marked_free',
            description: 'Page approved as free content',
            changes: [
                'is_paid' => ['old' => (bool) $page->is_paid, 'new' => false],
                'price' => ['old' => $page->price, 'new' => null],
                'visibility' => ['old' => $page->metadata->visibility ?? null, 'new' => 'public'],
                'changed_by' => $editorId,
                'note' => $note,
            ],
            includeSnapshot: true
        );

        return $updated;
    }

    public function rejectPremium(Page $page, int $editorId, string $reason): Page
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A rejection reason is required.');
        }
        
        $now = date('Y-m-d H:i:s');

        $this->pageRepository->update($page->id, [
            'is_paid' => false,
            'price' => null,

            'premium_rejected_at' => $now,
            'premium_rejected_by' => $editorId,
            'premium_rejection_reason' => $reason,

            'premium_approved_at' => null,
            'premium_approved_by' => null,
            'premium_approval_note' => null,
        ]);

        $this->metadataRepository->createOrUpdate($page->id, [
            'visibility' => 'free',
        ]);

        $updated = $this->pageRepository->find($page->id);

        if (!$updated) {
            throw new \RuntimeException("Updated page [{$page->id}] could not be loaded.");
        }

        $this->historyService->logPageAction(
            pageId: $page->id,
            action: 'premium_rejected',
            description: 'Premium monetisation rejected',
            changes: [
                'is_paid' => ['old' => (bool) $page->is_paid, 'new' => false],
                'price' => ['old' => $page->price, 'new' => null],
                'visibility' => ['old' => $page->metadata->visibility ?? null, 'new' => 'public'],
                'premium_rejected_by' => $editorId,
                'premium_rejection_reason' => $reason,
            ],
            includeSnapshot: true
        );

        $this->eventDispatcher->dispatch(
            new PremiumMonetisationRejectedEvent($updated, $editorId, $reason)
        );

        return $updated;
    }
}