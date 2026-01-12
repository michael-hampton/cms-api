<?php

namespace App\Actions\Pages;

use App\Services\Cms\PageService;
use Exception;

class BulkUpdatePage
{
    public function __construct(private readonly PageService $pageService)
    {

    }

    public function handle(array $pageIds, array $updateData, int $siteId): array
    {
        $results = [];
        foreach ($pageIds as $pageId) {
            try {
                $updateData['id'] = $pageId;
                $results[$pageId] = $this->pageService->updatePageWithAllData($pageId, $updateData, $siteId);
            } catch (Exception $e) {
                $results[$pageId] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }
}