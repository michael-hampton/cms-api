<?php

namespace App\Actions;

use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\PageRepository;

class BulkExportPages
{
    public function __construct(
        private readonly PageRepository  $pageRepository,
        private readonly BlockRepository $blockRepository
    )
    {
    }

    public function handle(array $pageIds, string $format = 'json', bool $includeBlocks = true): string
    {
        $pages = [];

        foreach ($pageIds as $pageId) {
            $page = $this->pageRepository->getCompletePageData($pageId);

            if (!$page) {
                continue;
            }

            $pageData = [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'published_at' => $page->published_at,
                'author_id' => $page->author_id,
            ];

            if ($includeBlocks) {
                $blocks = $this->blockRepository->getBlocksForPage($pageId);
                $pageData['blocks'] = $blocks->map(function ($block) {
                    return [
                        'type' => $block->type,
                        'data' => $block->data,
                        'order' => $block->order,
                    ];
                })->toArray();
            }

            $pages[] = $pageData;
        }

        return match ($format) {
            'csv' => $this->exportToCsv($pages),
            'json' => json_encode($pages, JSON_PRETTY_PRINT),
            default => json_encode($pages, JSON_PRETTY_PRINT),
        };
    }

    private function exportToCsv(array $pages): string
    {
        if (empty($pages)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Get headers from first page (excluding blocks for CSV)
        $firstPage = $pages[0];
        unset($firstPage['blocks']);
        fputcsv($output, array_keys($firstPage));

        // Write data rows
        foreach ($pages as $page) {
            unset($page['blocks']);
            fputcsv($output, $page);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}