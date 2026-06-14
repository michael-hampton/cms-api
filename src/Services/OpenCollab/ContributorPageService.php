<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\Pages\PageStatus;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Models\Page;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\Pages\PageAuthorRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\Cms\Pages\PageService;

/**
 * Contributor-scoped page operations.
 */
class ContributorPageService
{
    public function __construct(
        private readonly PageService $pageService,
        private readonly PageRepository $pageRepository,
        private readonly ArticleApprovalService $articleApprovalService,
        private readonly ActivityRepository $activityRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly PageAuthorRepository $pageAuthorRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function createPage(array $requestData, int $contributorId, int $siteId): Page
    {
        $requestApproval = $this->requestsApproval($requestData);
        $requestData = $this->injectContributorDefaults(
            $requestData,
            $contributorId,
            PageStatus::DRAFT->value,
        );

        $page = $this->pageService->createPageWithAllData($requestData, $siteId);

        $this->attachGuestAuthor($page, $contributorId, $siteId);

        $this->recordActivity(
            siteId: $siteId,
            userId: $contributorId,
            type: ActivityEventType::ArticleCreated,
            payload: ['page_id' => $page->id, 'title' => $page->title],
        );

        if ($requestApproval) {
            return $this->articleApprovalService->submitForReview(
                (int) $page->id,
                $contributorId,
            );
        }

        return $page;
    }

    public function updatePage(int $pageId, array $requestData, int $contributorId, int $siteId): Page
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page || (int) $page->contributor_id !== $contributorId) {
            throw new UnauthorisedPageAccessException();
        }

        $requestApproval = $this->requestsApproval($requestData);
        $isResubmission = $requestApproval && in_array(
            $page->status,
            [PageStatus::REJECTED->value, PageStatus::ON_HOLD->value],
            true,
        );

        $statusForSave = $isResubmission
            ? $page->status
            : PageStatus::DRAFT->value;

        $requestData = $this->injectContributorDefaults(
            $requestData,
            $contributorId,
            $statusForSave,
        );
        $requestData['id'] = $pageId;

        $updated = $this->pageService->updatePageWithAllData(
            $pageId,
            $requestData,
            $siteId,
            $page,
        );

        $this->recordActivity(
            siteId: $siteId,
            userId: $contributorId,
            type: ActivityEventType::ArticleUpdated,
            payload: ['page_id' => $updated->id, 'title' => $updated->title],
        );

        if (!$requestApproval) {
            return $updated;
        }

        return $isResubmission
            ? $this->articleApprovalService->resubmit($pageId, $contributorId)
            : $this->articleApprovalService->submitForReview($pageId, $contributorId);
    }

    public function deletePage(int $pageId, int $contributorId): void
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page || (int) $page->contributor_id !== $contributorId) {
            throw new UnauthorisedPageAccessException();
        }

        $siteId = (int) $page->site_id;
        $title = $page->title;

        $this->pageRepository->delete($pageId);

        $this->recordActivity(
            siteId: $siteId,
            userId: $contributorId,
            type: ActivityEventType::ArticleUpdated,
            payload: ['page_id' => $pageId, 'title' => $title, 'action' => 'deleted'],
        );
    }

    private function requestsApproval(array $requestData): bool
    {
        $requestedStatus = strtolower((string) (
            $requestData['status']
            ?? $requestData['forms']['meta']['status']
            ?? 'draft'
        ));

        return (bool) ($requestData['submit_for_approval'] ?? $requestData['request_approval'] ?? false)
            || in_array(
                $requestedStatus,
                [
                    PageStatus::PUBLISHED->value,
                    PageStatus::WAITING_APPROVAL->value,
                    'published',
                    'waiting approval',
                ],
                true,
            );
    }

    private function injectContributorDefaults(
        array $requestData,
        int $contributorId,
        string $status,
    ): array {
        $requestData['contributor_id'] = $contributorId;
        $requestData['is_public_contribution'] = true;
        $requestData['suppress_workflow_notifications'] = true;
        $requestData['status'] = $status;
        $requestData['forms']['meta']['status'] = $status;

        return $requestData;
    }

    private function recordActivity(
        int $siteId,
        int $userId,
        ActivityEventType $type,
        array $payload = [],
    ): void {
        try {
            $this->activityRepository->record($siteId, $userId, $type, $payload);
        } catch (\Throwable) {
            // Activity is supplementary and must not block the page operation.
        }
    }

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
            // Page creation already succeeded; author linking is non-critical.
        }
    }

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
