<?php

namespace App\Services;

use App\Models\Page;
use App\Parsers\ZoneBlockParser;
use App\Repositories\BlockRepository;

class PageRenderService
{
    public function __construct(
        private readonly BlockRepository    $blockRepository,
        private readonly BlockParserService $blockParserService,
        private readonly ZoneBlockParser    $zoneBlockParser,
    )
    {
    }

    /**
     * Renders a page with proper separation of main content and sidebar blocks
     * Returns an array with 'main' and 'sidebar' HTML
     */
    public function renderPage(Page $page): array
    {
        $mainHtml = '';
        $sidebarHtml = '';

        // Build zones HTML and get list of used block IDs
        $zonesResult = $this->zoneBlockParser->buildZonesHtml($page);

        $usedBlockIds = $zonesResult['usedBlockIds'];

        // Render remaining blocks that weren't used in zones
        $pageBlocks = $this->blockRepository->getPageBlocks($page->id);

        foreach ($pageBlocks as $block) {
            // Skip blocks that were already rendered in zones
            if (in_array($block->id, $usedBlockIds)) {
                continue;
            }

            try {
                $blockHtml = $this->blockParserService->buildBlock(
                    $block->page_id,
                    array_merge($block->data, ['type' => $block->type]),
                    $block->order
                );

                // Determine if this block should go in sidebar or main content
                $context = $block->data['context'] ?? 'default';

                if ($context === 'sidebar') {
                    $sidebarHtml .= $blockHtml;
                } else {
                    $mainHtml .= $blockHtml;
                }
            } catch (\Exception $e) {
                // Log error but continue rendering
                error_log("Failed to render block {$block->id}: {$e->getMessage()}");
            }
        }

        $mainHtml .= $zonesResult['html'];

        return [
            'main' => $mainHtml,
            'sidebar' => $sidebarHtml,
            'hasSidebar' => !empty($sidebarHtml)
        ];
    }
}