<?php

namespace App\Actions;

use App\Repositories\PageRepository;

class BulkChangePageAuthor
{
    public function __construct(
        private readonly PageRepository $pageRepository
    )
    {
    }

    public function handle(array $pageIds, int $authorId): array
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

                $this->pageRepository->update($pageId, [
                    'author_id' => $authorId
                ]);

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