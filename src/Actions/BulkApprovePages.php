<?php

namespace App\Actions;

use App\Services\Cms\PageService;

class BulkApprovePages
{
    public function __construct(private readonly PageService $pageService)
    {

    }

    /**
     * Bulk approve pages
     */
    public function handle(array $pageIds, int $userId): array
    {
        $results = [];

        foreach ($pageIds as $pageId) {
            try {
                $this->pageService->approvePage($pageId, $userId);
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