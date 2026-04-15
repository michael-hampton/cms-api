<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\OpenCollab\RejectionReason;
use App\Enums\Pages\PageStatus;
use App\Events\OpenCollab\ArticleApprovedEvent;
use App\Events\OpenCollab\ArticleRejectedEvent;
use App\Events\OpenCollab\ArticleSubmittedForReviewEvent;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\OpenCollab\Policies\ContributorPolicy;

/**
 * Governs the contributor article moderation lifecycle.
 *
 * Policy enforcement is injected via ContributorPolicy so the permission
 * logic is not duplicated here. The service throws OnboardingIncompleteException
 * when the policy blocks an action — callers translate this to the appropriate
 * HTTP response.
 *
 * Allowed transitions:
 *   draft / on_hold  → waiting_approval  (contributor submits — policy checked)
 *   waiting_approval → published          (admin approves — no policy check needed)
 *   waiting_approval → on_hold            (admin rejects)
 */
class ArticleApprovalService
{
    public function __construct(
        private readonly PageRepository     $pageRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly EventDispatcher    $eventDispatcher,
        private readonly Database           $database,
        private readonly ContributorPolicy $policy,
        private readonly SiteRepository    $siteRepository
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
        $page = $this->pageRepository->find($pageId);

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

        $submittableStatuses = [
            PageStatus::DRAFT->value,
            PageStatus::ON_HOLD->value,
        ];

        if (!in_array($page->status, $submittableStatuses, true)) {
            throw new \InvalidArgumentException(
                "Article [{$pageId}] cannot be submitted from status [{$page->status}]."
            );
        }

        $page = $this->database->transaction(function () use ($page, $contributorId): Page {
            $this->pageRepository->update($page->id, [
                'status' => PageStatus::WAITING_APPROVAL->value,
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);

            $this->activityRepository->record(
                siteId: $page->site_id,
                userId: $contributorId,
                type: ActivityEventType::ArticleUpdated,
                payload: ['page_id' => $page->id, 'action' => 'submitted_for_review'],
            );

            return $this->pageRepository->find($page->id);
        });

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
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \InvalidArgumentException("Page [{$pageId}] not found.");
        }

        if ($page->status !== PageStatus::WAITING_APPROVAL->value) {
            throw new \InvalidArgumentException(
                "Article [{$pageId}] is not awaiting approval (status: {$page->status})."
            );
        }

        $page = $this->database->transaction(function () use ($page, $adminId): Page {
            $this->pageRepository->update($page->id, [
                'status' => PageStatus::PUBLISHED->value,
                'approved_by' => $adminId,
                'approved_at' => date('Y-m-d H:i:s'),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'rejection_notes' => null,
                'published_at' => date('Y-m-d H:i:s'),
            ]);

            $this->activityRepository->record(
                siteId: $page->site_id,
                userId: (int)$page->contributor_id,
                type: ActivityEventType::ArticlePublished,
                payload: ['page_id' => $page->id, 'approved_by' => $adminId],
            );

            return $this->pageRepository->find($page->id);
        });

        $this->eventDispatcher->dispatch(new ArticleApprovedEvent($page, $adminId));

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
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \InvalidArgumentException("Page [{$pageId}] not found.");
        }

        if ($page->status !== PageStatus::WAITING_APPROVAL->value) {
            throw new \InvalidArgumentException(
                "Article [{$pageId}] is not awaiting approval (status: {$page->status})."
            );
        }

        $page = $this->database->transaction(function () use ($page, $adminId, $reason, $notes): Page {
            $this->pageRepository->update($page->id, [
                'status' => PageStatus::ON_HOLD->value,
                'rejected_by' => $adminId,
                'rejected_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => $reason->value,
                'rejection_notes' => $notes,
                'approved_by' => null,
                'approved_at' => null,
                'resubmission_count' => (int)$page->resubmission_count,
            ]);

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

            return $this->pageRepository->find($page->id);
        });

        $this->eventDispatcher->dispatch(new ArticleRejectedEvent($page, $adminId, $reason, $notes));

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
        $page = $this->pageRepository->find($pageId);

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

        if ($page->status !== PageStatus::ON_HOLD->value) {
            throw new \InvalidArgumentException(
                "Article [{$pageId}] cannot be resubmitted from status [{$page->status}]."
            );
        }

        $page = $this->database->transaction(function () use ($page, $contributorId): Page {
            $this->pageRepository->update($page->id, [
                'status' => PageStatus::WAITING_APPROVAL->value,
                'submitted_at' => date('Y-m-d H:i:s'),
                'resubmission_count' => ((int)$page->resubmission_count) + 1,
            ]);

            $this->activityRepository->record(
                siteId: $page->site_id,
                userId: $contributorId,
                type: ActivityEventType::ArticleUpdated,
                payload: [
                    'page_id' => $page->id,
                    'action' => 'resubmitted',
                    'resubmission_count' => ((int)$page->resubmission_count) + 1,
                ],
            );

            return $this->pageRepository->find($page->id);
        });

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
        return $this->pageRepository
            ->query()
            ->where('site_id', $siteId)
            ->where('status', PageStatus::WAITING_APPROVAL->value)
            ->whereNotNull('contributor_id')
            ->orderBy('submitted_at')
            ->get();
    }
}