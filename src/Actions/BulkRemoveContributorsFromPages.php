<?php

namespace App\Actions;

use App\Repositories\Cms\PageAuthorRepository;
use App\Repositories\Cms\PageRepository;

class BulkRemoveContributorsFromPages
{
    public function __construct(
        private readonly PageRepository       $pageRepository,
        private readonly PageAuthorRepository $pageAuthorRepository
    )
    {
    }

    public function handle(array $pageIds, array $contributorIds, int $siteId, string $role = 'contributor'): array
    {
        $results = [];

        foreach ($pageIds as $pageId) {
            try {
                $page = $this->pageRepository->find($pageId);

                if (!$page) {
                    $results[$pageId] = [
                        'success' => false,
                        'error' => 'Page not found'
                    ];
                    continue;
                }

                // Get existing contributors for this role
                $existingContributorIds = $this->pageAuthorRepository->getAuthorsForPage($pageId, 'contributor');

                // Remove specified contributors
                $remainingContributorIds = array_diff($existingContributorIds, $contributorIds);

                // Sync remaining contributors
                $this->pageAuthorRepository->syncAuthors($pageId, array_values($remainingContributorIds), $role, $siteId);

                $results[$pageId] = ['success' => true];
            } catch (\Exception $e) {
                $results[$pageId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}