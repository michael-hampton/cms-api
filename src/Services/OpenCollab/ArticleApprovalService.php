<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\OpenCollab\RejectionReason;
use App\Events\OpenCollab\ArticleSubmittedForReviewEvent;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\Page;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\Cms\Pages\PageService;
use App\Services\OpenCollab\Policies\ContributorPolicy;

/**
 * Governs the contributor article moderation lifecycle.
 *
 * Policy enforcement is injected via ContributorPolicy so the permission
 * logic is not duplicated here. The service throws OnboardingIncompleteException
 * when the policy blocks an action — callers translate this to the appropriate
 * HTTP response.
 *
 * Page state transitions are delegated to PageService. This service only checks
 * Open Collab policy and records Open Collab activity.
 */
class ArticleApprovalService
{
    public function __construct(
        private readonly PageService            $pageService,
        private readonly ActivityRepository      $activityRepository,
        private readonly EventDispatcher         $eventDispatcher,
        private readonly ContributorPolicy       $policy,
        private readonly SiteRepository          $siteRepository,
        private readonly NotificationDispatcher  $notificationDispatcher,
        private readonly UserRepositoryInterface $userRepository,

    )
    {
    }

    /**
     * Contributor submits their article for review.
     * Transitions: draft|on_hold → waiting_approval
     *
     * @throws UnauthorisedPageAccessException if the contributor does not own the page
     * @throws OnboardingIncompleteException   if compliance steps are outstanding
     * @throws \InvalidArgumentException       if the page cannot be submitted
     */
    public function submitForReview(int $pageId, int $contributorId): Page
    {
        $page = $this->pageService->findPage($pageId);

        if (!$page || (int)$page->contributor_id !== $contributorId) {
            throw new UnauthorisedPageAccessException();
        }

        // Load site to pass to the policy. Falls back to a minimal Site model
        // if the page site_id is not resolvable (shouldn't happen in practice).
        $site = $this->siteRepository->find($page->site_id);

        if (!$site) {
            throw new \InvalidArgumentException("Site [{$page->site_id}] not found.");
        }

        if (!$this->policy->canSubmitForReview($contributorId, $site)) {
            $pending = [];

            throw new OnboardingIncompleteException($pending);
        }

        $page = $this->pageService->submitPageForReview($pageId, $contributorId);

        $this->activityRepository->record(
            siteId: $page->site_id,
            userId: $contributorId,
            type: ActivityEventType::ArticleUpdated,
            payload: ['page_id' => $page->id, 'action' => 'submitted_for_review'],
        );

        $this->eventDispatcher->dispatch(
            new ArticleSubmittedForReviewEvent($page, $contributorId)
        );

        return $page;
    }

    /**
     * Admin approves a contributor article.
     * Transitions: waiting_approval → published
     *
     * @throws \InvalidArgumentException if the article is not in waiting_approval
     */
    public function approve(int $pageId, int $adminId): Page
    {
        $page = $this->pageService->approvePage($pageId, $adminId);

        $this->activityRepository->record(
            siteId: $page->site_id,
            userId: (int)$page->contributor_id,
            type: ActivityEventType::ArticlePublished,
            payload: ['page_id' => $page->id, 'approved_by' => $adminId],
        );

        return $page;
    }

    /**
     * Admin rejects a contributor article.
     * Transitions: waiting_approval → on_hold
     *
     * @throws \InvalidArgumentException if the article is not in waiting_approval
     */
    public function reject(int $pageId, int $adminId, RejectionReason $reason, ?string $notes = null): Page
    {
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

        return $page;
    }

    /**
     * Contributor resubmits an article after rejection.
     * Transitions: on_hold → waiting_approval
     *
     * @throws UnauthorisedPageAccessException if the contributor does not own the page
     * @throws OnboardingIncompleteException   if compliance steps are outstanding
     * @throws \InvalidArgumentException       if the article is not on_hold
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

        $this->eventDispatcher->dispatch(
            new ArticleSubmittedForReviewEvent($page, $contributorId)
        );

        return $page;
    }

    /**
     * Returns all articles awaiting approval for a site.
     */
    public function pendingReviewForSite(int $siteId): \App\Framework\Support\Collection
    {
        return $this->pageService->pendingReviewForSite($siteId);
    }
}
