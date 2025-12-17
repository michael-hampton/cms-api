<?php

namespace App\Services;

use App\Framework\Container;
use App\Models\Page;
use App\Parsers\ZoneBlockParser;
use App\Repositories\BlockRepository;

class PageRenderService
{
    public function __construct(
        private readonly BlockRepository    $blockRepository,
        private readonly BlockParserService $blockParserService
    )
    {
    }

    public function renderPage(Page $page): string
    {
        $html = '';

        // Get zone parser
        $zoneParser = Container::getInstance()->make(ZoneBlockParser::class);

        if ($zoneParser instanceof ZoneBlockParser) {
            // Build zones HTML and get list of used block IDs
            $zonesResult = $zoneParser->buildZonesHtml($page);
            $html .= $zonesResult['html'];
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

                    $html .= $blockHtml;
                } catch (\Exception $e) {
                    // Log error but continue rendering
                    error_log("Failed to render block {$block->id}: {$e->getMessage()}");
                }
            }
        } else {
            // Fallback: render all blocks normally if zone parser not available
            $pageBlocks = $this->blockRepository->getPageBlocks($page->id);

            foreach ($pageBlocks as $block) {
                try {
                    $blockHtml = $this->blockParserService->buildBlock(
                        $block->page_id,
                        array_merge($block->data, ['type' => $block->type]),
                        $block->order
                    );

                    $html .= $blockHtml;
                } catch (\Exception $e) {
                    error_log("Failed to render block {$block->id}: {$e->getMessage()}");
                }
            }
        }

        return $html;
    }
}