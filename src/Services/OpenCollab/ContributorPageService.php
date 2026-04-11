<?php

namespace App\Services\OpenCollab;

use App\Enums\Pages\PageStatus;
use App\Events\OpenCollab\PagePublishedByContributorEvent;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Events\EventDispatcher;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\PageService;

/**
 * Contributor-scoped page operations.
 *
 * This service enforces ownership rules and sets contributor-specific fields
 * before delegating the actual persistence to the existing PageService.
 *
 * It does NOT duplicate PageService logic. It wraps it.
 *
 * Responsibilities:
 *   - Enforce: contributors can only create/edit/publish their own pages
 *   - Set contributor_id and is_public_contribution on create
 *   - Fire PagePublishedByContributorEvent when a page transitions to published
 *
 * Services MUST NOT be called for side effects. PageService is called for
 * persistence orchestration, which is its job.
 */
class ContributorPageService
{
    public function __construct(
        private readonly PageService     $pageService,
        private readonly PageRepository  $pageRepository,
        private readonly EventDispatcher $eventDispatcher,
    )
    {
    }

    /**
     * Create a new page owned by this contributor.
     */
    public function createPage(array $requestData, int $contributorId, int $siteId): Page
    {
        $requestData = $this->injectContributorDefaults($requestData, $contributorId);

        return $this->pageService->createPageWithAllData($requestData, $siteId);
    }

    /**
     * Merges contributor-specific fields that must always be present on
     * pages created or modified through this service.
     */
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

        $this->pageRepository->delete($pageId);
    }
}