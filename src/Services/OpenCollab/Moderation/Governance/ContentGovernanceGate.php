<?php

namespace App\Services\OpenCollab\Moderation\Governance;

use App\Enums\OpenCollab\EscalationCategory;
use App\Enums\OpenCollab\EscalationStatus;
use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskType;
use App\Enums\Pages\PageStatus;
use App\Exceptions\OpenCollab\GovernanceCheckFailedException;
use App\Repositories\Cms\ImageRepository;
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Repositories\OpenCollab\RiskMarkerRepository;
use App\Services\Cms\Pages\PageService;

class ContentGovernanceGate
{
    public function __construct(
        private readonly PageService $pageService,
        private readonly ModerationQueueRepository $queueRepository,
        private readonly RiskMarkerRepository $riskMarkerRepository,
        private readonly ModerationEscalationRepository $escalationRepository,
        private readonly ImageRepository $imageRepository,
    ) {
    }

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

        if ($page->status !== PageStatus::WAITING_APPROVAL->value) {
            $failures[] = new GovernanceFailure(
                'page_not_awaiting_approval',
                "Page is in status [{$page->status}], not waiting_approval."
            );
        }

        $outstandingRisks = $this->riskMarkerRepository->outstandingForPage(
            (int) $page->site_id,
            (int) $page->id,
        );

        foreach ($outstandingRisks as $marker) {
            $severity = $marker->severity instanceof RiskSeverity
                ? $marker->severity
                : RiskSeverity::from((string) $marker->severity);

            $riskType = $marker->risk_type instanceof RiskType
                ? $marker->risk_type->value
                : (string) $marker->risk_type;

            if ($severity === RiskSeverity::Critical) {
                $failures[] = new GovernanceFailure(
                    'unresolved_critical_risk',
                    "Unresolved critical {$riskType} risk marker (id {$marker->id}).",
                );
                continue;
            }

            if ($severity === RiskSeverity::High) {
                $failures[] = new GovernanceFailure(
                    'unresolved_high_risk',
                    "Unresolved high {$riskType} risk marker (id {$marker->id}).",
                );
            }
        }

        $openEscalations = $this->escalationRepository->openForPage($page->site_id, $page->id);

        foreach ($openEscalations as $escalation) {
            $status = $escalation->status instanceof EscalationStatus
                ? $escalation->status
                : EscalationStatus::from((string) $escalation->status);

            if (!in_array($status, [
                EscalationStatus::Resolved,
                EscalationStatus::Closed,
                EscalationStatus::Cancelled,
            ], true)) {
                $category = $escalation->category instanceof EscalationCategory
                    ? $escalation->category->value
                    : (string) $escalation->category;

                $failures[] = new GovernanceFailure(
                    'unresolved_escalation',
                    "Open escalation (id {$escalation->id}, category {$category}) must be resolved before approval."
                );
            }
        }

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

                if ((int) $image->site_id !== (int) $page->site_id) {
                    $failures[] = new GovernanceFailure(
                        'image_wrong_site',
                        "Selected image [{$imageId}] does not belong to this site."
                    );
                }
            }
        }

        $queueEntry = $this->queueRepository->openEntryForPage($page->site_id, $page->id);

        if ($queueEntry !== null) {
            $queueStatus = $queueEntry->status instanceof ModerationQueueStatus
                ? $queueEntry->status
                : ModerationQueueStatus::from((string) $queueEntry->status);

            if ($queueStatus === ModerationQueueStatus::Escalated) {
                $failures[] = new GovernanceFailure(
                    'queue_escalated',
                    'Queue entry is in escalated state.'
                );
            }
        }

        return empty($failures)
            ? GovernanceCheckResult::pass()
            : GovernanceCheckResult::fail($failures);
    }
}
