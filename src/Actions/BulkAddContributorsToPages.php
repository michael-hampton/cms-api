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

    public function handle(array $pageIds, array $contributorIds): array
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

                // Get existing contributors
                $existingContributorIds = $this->pageAuthorRepository
                    ->getAuthorsForPage($pageId)
                    ->pluck('author_id')
                    ->toArray();

                // Merge with new contributors
                $allContributorIds = array_unique(
                    array_merge($existingContributorIds, $contributorIds)
                );

                // Sync contributors
                $this->pageAuthorRepository->syncAuthors($pageId, $allContributorIds);

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