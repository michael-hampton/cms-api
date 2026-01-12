<?php

namespace App\Actions\Pages;

use App\Repositories\Cms\PageAuthorRepository;
use App\Repositories\Cms\PageRepository;

class BulkChangePageAuthors
{
    public function __construct(
        private readonly PageRepository       $pageRepository,
        private readonly PageAuthorRepository $pageAuthorRepository
    )
    {
    }

    public function handle(array $pageIds, int $authorId, int $siteId, string $role = 'primary'): array
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

                // Use syncAuthors to replace all authors for this role with the new author
                $this->pageAuthorRepository->syncAuthors($pageId, [$authorId], $role, $siteId);

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