<?php

namespace App\Actions;

use App\Repositories\PageAuthorRepository;
use App\Repositories\PageRepository;

class BulkAddContributorsToPages
{
    public function __construct(
        private readonly PageRepository       $pageRepository,
        private readonly PageAuthorRepository $pageAuthorRepository
    )
    {
    }

    // BulkAddContributorsToPages.php
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

                // Merge with new contributors
                $allContributorIds = array_unique(
                    array_merge($existingContributorIds, $contributorIds)
                );

                // Sync contributors
                $this->pageAuthorRepository->syncAuthors($pageId, $allContributorIds, $role, $siteId);

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