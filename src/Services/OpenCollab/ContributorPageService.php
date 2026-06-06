<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\Pages\PageStatus;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Events\EventDispatcher;
use App\Models\Page;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\Pages\PageAuthorRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Repositories\OpenCollab\RbacRepository;
use App\Repositories\UserNotificationRepository;
use App\Services\Cms\Pages\PageService;

/**
 * Contributor-scoped page operations.
 *
 * Activity feed events are recorded here so contributor actions
 * (create, update, delete) appear in the dashboard activity feed.
 */
class ContributorPageService
{
    public function __construct(
        private readonly PageService        $pageService,
        private readonly PageRepository     $pageRepository,
        private readonly EventDispatcher    $eventDispatcher,
        private readonly ActivityRepository $activityRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly PageAuthorRepository    $pageAuthorRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserNotificationRepository $notificationRepository,
        private readonly RbacRepository $rbacRepository,
        private readonly SitePermissionResolver $permissionResolver,
    )
    {
    }

    /**
     * Create a new page owned by this contributor.
     */
    public function createPage(array $requestData, int $contributorId, int $siteId): Page
    {
        $requestData = $this->injectContributorDefaults($requestData, $contributorId);

        $page = $this->pageService->createPageWithAllData($requestData, $siteId);

        $this->attachGuestAuthor($page, $contributorId, $siteId);

        // Record activity — non-critical, swallow failures
        $this->recordActivity(
            siteId: $siteId,
            userId: $contributorId,
            type: ActivityEventType::ArticleCreated,
            payload: ['page_id' => $page->id, 'title' => $page->title],
        );

        if ($page->status === PageStatus::WAITING_APPROVAL->value) {
            $this->notifyReviewers($page, $contributorId, $siteId);
        }

        return $page;
    }

    private function injectContributorDefaults(array $requestData, int $contributorId): array
    {
        $requestedStatus = strtolower((string)($requestData['status'] ?? $requestData['forms']['meta']['status'] ?? 'draft'));
        $requestApproval = (bool)($requestData['submit_for_approval'] ?? $requestData['request_approval'] ?? false)
            || in_array($requestedStatus, [PageStatus::PUBLISHED->value, PageStatus::WAITING_APPROVAL->value, 'published', 'waiting approval'], true);
        $safeStatus = $requestApproval ? PageStatus::WAITING_APPROVAL->value : PageStatus::DRAFT->value;

        $requestData['contributor_id'] = $contributorId;
        $requestData['is_public_contribution'] = true;
        $requestData['suppress_workflow_notifications'] = true;
        $requestData['status'] = $safeStatus;
        $requestData['forms']['meta']['status'] = $safeStatus;
        return $requestData;
    }

    /**
     * Update a page — contributor must own it.
     *
     * @throws UnauthorisedPageAccessException
     */
    public function updatePage(int $pageId, array $requestData, int $contributorId, int $siteId): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page || (int)$page->contributor_id !== $contributorId) {
            throw new UnauthorisedPageAccessException();
        }

        $requestData = $this->injectContributorDefaults($requestData, $contributorId);
        $requestData['id'] = $pageId;

        $wasWaitingApproval = $page->status === PageStatus::WAITING_APPROVAL->value;
        $updated = $this->pageService->updatePageWithAllData($pageId, $requestData, $siteId, $page);

        $this->recordActivity(
            siteId: $siteId,
            userId: $contributorId,
            type: ActivityEventType::ArticleUpdated,
            payload: ['page_id' => $updated->id, 'title' => $updated->title],
        );

        if (!$wasWaitingApproval && $updated->status === PageStatus::WAITING_APPROVAL->value) {
            $this->notifyReviewers($updated, $contributorId, $siteId);
        }

        return $updated;
    }

    private function notifyReviewers(Page $page, int $contributorId, int $siteId): void
    {
        $type = 'page_submitted_for_approval';

        foreach ($this->rbacRepository->usersForSite($siteId) as $user) {
            $userId = (int)($user['id'] ?? 0);

            if (!$userId || (isset($user['is_active']) && !(bool)$user['is_active'])) {
                continue;
            }

            if (
                !$this->permissionResolver->allows($userId, $siteId, 'pages.review')
                && !$this->permissionResolver->allows($userId, $siteId, 'content.review')
            ) {
                continue;
            }

            $this->notificationRepository->create($userId, $type, [
                'page_id' => (int)$page->id,
                'page_title' => (string)$page->title,
                'site_id' => $siteId,
                'content_type' => 'page',
                'content_id' => (int)$page->id,
                'content_title' => (string)$page->title,
                'notification_type' => $type,
                'action_user_id' => $contributorId,
                'url' => "/admin/pages/{$page->id}/edit",
            ]);
        }
    }

    /**
     * Delete a page — contributor must own it.
     *
     * @throws UnauthorisedPageAccessException
     */
    public function deletePage(int $pageId, int $contributorId): void
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page || (int)$page->contributor_id !== $contributorId) {
            throw new UnauthorisedPageAccessException();
        }

        $siteId = (int)$page->site_id;
        $title = $page->title;

        $this->pageRepository->delete($pageId);

        $this->recordActivity(
            siteId: $siteId,
            userId: $contributorId,
            type: ActivityEventType::ArticleUpdated,
            payload: ['page_id' => $pageId, 'title' => $title, 'action' => 'deleted'],
        );
    }

    /**
     * Record activity, swallowing any errors since activity recording
     * is non-critical and must never block the primary operation.
     */
    private function recordActivity(int $siteId, int $userId, ActivityEventType $type, array $payload = []): void
    {
        try {
            $this->activityRepository->record($siteId, $userId, $type, $payload);
        } catch (\Throwable) {
            // Non-critical — do not propagate
        }
    }

    /**
     * Resolves or creates a guest Author for the contributor and links it to
     * the page via PageAuthorRepository.
     *
     * Uses UserRepositoryInterface to look up the contributor — no static calls.
     * Uses AuthorRepository::findByEmail() to check for an existing author.
     * Uses AuthorRepository::isSlugTaken() to generate a unique guest slug.
     * Uses PageAuthorRepository::link() for the pivot write.
     */
    private function attachGuestAuthor(Page $page, int $contributorId, int $siteId): void
    {
        try {
            $user = $this->userRepository->find($contributorId);

            if (!$user) {
                return;
            }

            $author = $this->authorRepository->findByEmail($user->email);

            if (!$author) {
                $author = $this->authorRepository->create([
                    'name' => $user->name ?? $user->email,
                    'email' => $user->email,
                    'slug' => $this->generateGuestSlug($user->name ?? $user->email),
                    'site_id' => $siteId,
                    'is_guest' => true,
                    'bio' => '',
                    'is_active' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->pageAuthorRepository->link($page->id, $author->id);
        } catch (\Throwable) {
            // Non-critical — page creation already succeeded.
        }
    }

    /**
     * Generates a unique slug prefixed with 'guest-'.
     * Uniqueness is checked via AuthorRepository::isSlugTaken() — no static calls.
     */
    private function generateGuestSlug(string $name): string
    {
        $base = 'guest-' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
        $base = trim($base, '-');
        $slug = $base;
        $counter = 1;

        while ($this->authorRepository->isSlugTaken($slug)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
