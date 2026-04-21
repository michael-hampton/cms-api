<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\Pages\PageStatus;
use App\Events\OpenCollab\PagePublishedByContributorEvent;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Events\EventDispatcher;
use App\Models\Page;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\Pages\PageAuthorRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;
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

        return $page;
    }

    private function injectContributorDefaults(array $requestData, int $contributorId): array
    {
        $requestData['contributor_id'] = $contributorId;
        $requestData['is_public_contribution'] = true;
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

        $wasPublished = $page->status === PageStatus::PUBLISHED->value;
        $requestData = $this->injectContributorDefaults($requestData, $contributorId);
        $requestData['id'] = $pageId;

        $updated = $this->pageService->updatePageWithAllData($pageId, $requestData, $siteId, $page);

        $isNowPublished = $updated->status === PageStatus::PUBLISHED->value;

        if (!$wasPublished && $isNowPublished) {
            $this->eventDispatcher->dispatch(
                new PagePublishedByContributorEvent($updated, $contributorId)
            );

            $this->recordActivity(
                siteId: $siteId,
                userId: $contributorId,
                type: ActivityEventType::ArticlePublished,
                payload: ['page_id' => $updated->id, 'title' => $updated->title],
            );
        } else {
            $this->recordActivity(
                siteId: $siteId,
                userId: $contributorId,
                type: ActivityEventType::ArticleUpdated,
                payload: ['page_id' => $updated->id, 'title' => $updated->title],
            );
        }

        return $updated;
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