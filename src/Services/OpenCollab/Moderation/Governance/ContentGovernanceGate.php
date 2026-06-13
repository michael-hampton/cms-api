<?php

namespace App\Services\OpenCollab\Moderation\Governance;

use App\Enums\Pages\PageStatus;
use App\Enums\OpenCollab\EscalationStatus;
use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Exceptions\OpenCollab\GovernanceCheckFailedException;
use App\Repositories\Cms\ImageRepositoryInterface; // ASSUMED: existing CMS image repo
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Repositories\OpenCollab\RiskMarkerRepository;
use App\Services\Cms\Pages\PageService;

/**
 * Read-only check, run immediately before approval. Performs no writes —
 * deciding/recording is the caller's job (ArticleApprovalService).
 *
 * MVP scope: implements the checks that have concrete data sources already
 * built in this PR (risk markers, escalations, queue state, page status,
 * CMS image existence/site match). The remaining checks from Ticket 10's
 * list (contributor identity, alt/credit text, "evidence" for uploads,
 * "moderation snapshot is current") depend on data models not introduced
 * here and are left as TODOs with clear extension points below.
 */
class ContentGovernanceGate
{
    public function __construct(
        private readonly PageService $pageService,
        private readonly ModerationQueueRepository $queueRepository,
        private readonly RiskMarkerRepository $riskMarkerRepository,
        private readonly ModerationEscalationRepository $escalationRepository,
        private readonly ImageRepositoryInterface $imageRepository,
    ) {
    }

    /**
     * @throws GovernanceCheckFailedException
     */
    public function assertCanApprove(int $pageId, int $adminId): void
    {
        $result = $this->check($pageId);

        if (!$result->passed) {
            throw new GovernanceCheckFailedException($result->failures);
        }
    }

    public function check(int $pageId): GovernanceCheckResult
    {
        $failures = [];

        $page = $this->pageService->findPage($pageId);

        if ($page === null) {
            return GovernanceCheckResult::fail([
                new GovernanceFailure('page_not_found', "Page [{$pageId}] not found."),
            ]);
        }

        // 1. Page is waiting approval
        if ($page->status !== PageStatus::WAITING_APPROVAL->value) {
            $failures[] = new GovernanceFailure(
                'page_not_awaiting_approval',
                "Page is in status [{$page->status}], not waiting_approval."
            );
        }

        // 2. Open risk markers (critical always blocks; high blocks per severity rule)
        $outstandingRisks = $this->riskMarkerRepository->outstandingForPage($page->site_id, $page->id);

        foreach ($outstandingRisks as $marker) {
            if ($marker->severity->value === 'critical') {
                $failures[] = new GovernanceFailure(
                    'unresolved_critical_risk',
                    "Unresolved critical {$marker->risk_type->value} risk marker (id {$marker->id})."
                );
            } elseif ($marker->severity->value === 'high') {
                $failures[] = new GovernanceFailure(
                    'unresolved_high_risk',
                    "Unresolved high {$marker->risk_type->value} risk marker (id {$marker->id})."
                );
            }
        }

        // 3. Open escalations
        $openEscalations = $this->escalationRepository->openForPage($page->site_id, $page->id);

        foreach ($openEscalations as $escalation) {
            if (!in_array($escalation->status, [EscalationStatus::Resolved, EscalationStatus::Closed, EscalationStatus::Cancelled], true)) {
                $failures[] = new GovernanceFailure(
                    'unresolved_escalation',
                    "Open escalation (id {$escalation->id}, category {$escalation->category->value}) must be resolved before approval."
                );
            }
        }

        // 4. Selected CMS images still exist and belong to this site
        // ASSUMED: Page (or its latest version) exposes a list of CMS image IDs
        // via $page->cmsImageIds() — adjust to your actual relation/accessor.
        if (method_exists($page, 'cmsImageIds')) {
            foreach ($page->cmsImageIds() as $imageId) {
                $image = $this->imageRepository->find($imageId);

                if ($image === null) {
                    $failures[] = new GovernanceFailure(
                        'image_missing',
                        "Selected image [{$imageId}] no longer exists in the CMS."
                    );
                    continue;
                }

                if ((int)$image->site_id !== (int)$page->site_id) {
                    $failures[] = new GovernanceFailure(
                        'image_wrong_site',
                        "Selected image [{$imageId}] does not belong to this site."
                    );
                }
            }
        }

        // 5. Queue entry status sanity (defends against stale snapshot)
        $queueEntry = $this->queueRepository->openEntryForPage($page->site_id, $page->id);

        if ($queueEntry !== null && $queueEntry->status === ModerationQueueStatus::Escalated) {
            $failures[] = new GovernanceFailure(
                'queue_escalated',
                'Queue entry is in escalated state.'
            );
        }

        // --- TODO (out of scope for this PR, no data model yet): ---
        // - contributor identity verification status
        // - required alt text / credit on images
        // - "new Open Collab uploads have evidence" (provenance docs)
        // - moderation snapshot freshness check (would need a version
        //   hash on the queue entry compared to current page_version_id)

        return empty($failures)
            ? GovernanceCheckResult::pass()
            : GovernanceCheckResult::fail($failures);
    }
}