<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\OpenCollab\ModerationActionType;
use App\Enums\OpenCollab\RejectionReason;
use App\Events\OpenCollab\ArticleSubmittedForReviewEvent;
use App\Events\OpenCollab\ChangesRequestedEvent;
use App\Exceptions\OpenCollab\GovernanceCheckFailedException;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\Page;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\Cms\Pages\PageService;
use App\Services\OpenCollab\Moderation\Governance\ContentGovernanceGate;
use App\Services\OpenCollab\Moderation\ModerationAuditService;
use App\Services\OpenCollab\Moderation\ModerationQueueService;
use App\Services\OpenCollab\Policies\ContributorPolicy;

/**
 * Governs the contributor article moderation lifecycle.
 *
 * Extended (not replaced) for the moderation queue / governance / audit
 * features: each transition now also updates the moderation queue and
 * writes an audit record, inside the same transaction as the page write.
 */
class ArticleApprovalService
{
    public function __construct(
        private readonly PageService $pageService,
        private readonly ActivityRepository $activityRepository,
        private readonly EventDispatcher $eventDispatcher,
        private readonly ContributorPolicy $policy,
        private readonly SiteRepository $siteRepository,
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ModerationQueueService $queueService,
        private readonly ModerationAuditService $auditService,
        private readonly ContentGovernanceGate $governanceGate,
        private readonly Database $database,
    ) {
    }

    /**
     * @throws UnauthorisedPageAccessException
     * @throws OnboardingIncompleteException
     */
    public function submitForReview(int $pageId, int $contributorId): Page
    {
        $page = $this->pageService->findPage($pageId);

        if (!$page || (int)$page->contributor_id !== $contributorId) {
            throw new UnauthorisedPageAccessException();
        }

        $site = $this->siteRepository->find($page->site_id);

        if (!$site) {
            throw new \InvalidArgumentException("Site [{$page->site_id}] not found.");
        }

        if (!$this->policy->canSubmitForReview($contributorId, $site)) {
            throw new OnboardingIncompleteException([]);
        }

        $page = $this->database->transaction(function () use ($pageId, $contributorId) {
            $page = $this->pageService->submitPageForReview($pageId, $contributorId);

            $this->activityRepository->record(
                siteId: $page->site_id,
                userId: $contributorId,
                type: ActivityEventType::ArticleUpdated,
                payload: ['page_id' => $page->id, 'action' => 'submitted_for_review'],
            );
            
            $this->queueService->enqueueForSubmission($page, $contributorId, isResubmission: false);

            return $page;
        });

        $this->eventDispatcher->dispatch(
            new ArticleSubmittedForReviewEvent($page, $contributorId)
        );

        return $page;
    }

    /**
     * @throws \InvalidArgumentException if the article is not waiting_approval
     * @throws GovernanceCheckFailedException if governance checks fail
     */
    public function approve(int $pageId, int $adminId): Page
    {
        // Read-only check BEFORE any writes — failure must not alter
        // page or queue status (Ticket 10 acceptance criteria).
        $this->governanceGate->assertCanApprove($pageId, $adminId);

        return $this->database->transaction(function () use ($pageId, $adminId) {
            $page = $this->pageService->approvePage($pageId, $adminId);

            $this->activityRepository->record(
                siteId: $page->site_id,
                userId: (int)$page->contributor_id,
                type: ActivityEventType::ArticlePublished,
                payload: ['page_id' => $page->id, 'approved_by' => $adminId],
            );

            $this->queueService->markApproved($page->id, $page->site_id);

            $this->auditService->record(
                siteId: $page->site_id,
                pageId: $page->id,
                actorUserId: $adminId,
                action: ModerationActionType::Approved,
            );

            return $page;
        });
    }

    /**
     * @throws \InvalidArgumentException if the article is not waiting_approval
     */
    public function reject(int $pageId, int $adminId, RejectionReason $reason, ?string $notes = null): Page
    {
        return $this->database->transaction(function () use ($pageId, $adminId, $reason, $notes) {
            $page = $this->pageService->rejectPage($pageId, $adminId, $reason->value, $notes);

            $this->activityRepository->record(
                siteId: $page->site_id,
                userId: (int)$page->contributor_id,
                type: ActivityEventType::ArticleUpdated,
                payload: [
                    'page_id' => $page->id,
                    'action' => 'rejected',
                    'reason' => $reason->value,
                    'rejected_by' => $adminId,
                ],
            );

            $this->queueService->markRejected($page->id, $page->site_id);

            $this->auditService->record(
                siteId: $page->site_id,
                pageId: $page->id,
                actorUserId: $adminId,
                action: ModerationActionType::Rejected,
                reasonCode: $reason->value,
                notes: $notes,
            );

            return $page;
        });
    }

    /**
     * @throws UnauthorisedPageAccessException
     * @throws OnboardingIncompleteException
     */
    public function resubmit(int $pageId, int $contributorId): Page
    {
        $page = $this->pageService->findPage($pageId);

        if (!$page || (int)$page->contributor_id !== $contributorId) {
            throw new UnauthorisedPageAccessException();
        }

        $site = $this->siteRepository->find($page->site_id);

        if (!$site) {
            throw new \InvalidArgumentException("Site [{$page->site_id}] not found.");
        }

        if (!$this->policy->canSubmitForReview($contributorId, $site)) {
            throw new OnboardingIncompleteException([]);
        }

        $nextCount = ((int)$page->resubmission_count) + 1;

        $page = $this->database->transaction(function () use ($pageId, $contributorId, $nextCount) {
            $page = $this->pageService->resubmitPageForReview($pageId, $contributorId);

            $this->activityRepository->record(
                siteId: $page->site_id,
                userId: $contributorId,
                type: ActivityEventType::ArticleUpdated,
                payload: [
                    'page_id' => $page->id,
                    'action' => 'resubmitted',
                    'resubmission_count' => $nextCount,
                ],
            );

            $this->queueService->enqueueForSubmission($page, $contributorId, isResubmission: true);

            return $page;
        });

        $this->eventDispatcher->dispatch(
            new ArticleSubmittedForReviewEvent($page, $contributorId)
        );

        return $page;
    }

    /**
     * Ticket 5 — moderator requests changes without full rejection.
     * Transitions: waiting_approval -> on_hold; queue -> changes_requested.
     *
     * @throws \InvalidArgumentException if the article is not waiting_approval
     */
    public function requestChanges(int $pageId, int $adminId, string $notes): Page
    {
        $page = $this->database->transaction(function () use ($pageId, $adminId, $notes) {
            $page = $this->pageService->requestChangesForPage($pageId, $adminId, $notes);

            $this->activityRepository->record(
                siteId: $page->site_id,
                userId: (int)$page->contributor_id,
                type: ActivityEventType::ArticleUpdated,
                payload: [
                    'page_id' => $page->id,
                    'action' => 'changes_requested',
                    'requested_by' => $adminId,
                ],
            );

            $this->queueService->markChangesRequested($page->id, $page->site_id);

            $this->auditService->record(
                siteId: $page->site_id,
                pageId: $page->id,
                actorUserId: $adminId,
                action: ModerationActionType::ChangesRequested,
                notes: $notes,
            );

            return $page;
        });

        $this->eventDispatcher->dispatch(
            new ChangesRequestedEvent($page, $adminId, $notes)
        );

        return $page;
    }

    public function pendingReviewForSite(int $siteId): \App\Framework\Support\Collection
    {
        return $this->pageService->pendingReviewForSite($siteId);
    }
}