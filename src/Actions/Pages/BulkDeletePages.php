<?php

namespace App\Actions\Pages;

use App\Services\Cms\PageService;
use Exception;

class BulkDeletePages
{
    public function __construct(private readonly Pageservice $pageService)
    {

    }

    public function handle(array $pageIds): array
    {
        $results = [];

        foreach ($pageIds as $pageId) {
            try {
                $this->pageService->deletePage($pageId);
                $results[$pageId] = ['success' => true];
            } catch (Exception $e) {
                $results[$pageId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}