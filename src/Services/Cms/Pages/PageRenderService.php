<?php

namespace App\Services\Cms\Pages;

use App\Models\Member;
use App\Models\Page;
use App\Parsers\PageGridRenderer;
use App\Parsers\ZoneBlockParser;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\Pages\PageGridRepository;
use App\Services\Adverts\PageVisibilityResolver;

class PageRenderService
{
    public function __construct(
        private readonly BlockRepository        $blockRepository,
        private readonly BlockParserService     $blockParserService,
        private readonly ZoneBlockParser        $zoneBlockParser,
        private readonly PageGridRepository     $pageGridRepository,
        private readonly PageVisibilityResolver $pageVisibilityResolver,
        private readonly PageGridRenderer       $pageGridRenderer,
    ) {
    }

    public function renderPage(Page $page, ?int $siteId = null, ?Member $member = null): array
    {
        $mainHtml = '';
        $sidebarHtml = '';

        $zonesResult = $this->zoneBlockParser->buildZonesHtml($page);
        $usedBlockIds = $zonesResult['usedBlockIds'];
        $pageBlocks = $this->blockRepository->getPageBlocks($page->id);
        $pageGrids = $this->pageGridRepository->getActiveGridForPage($page->id);

        $advertBlocks = $siteId && $this->supportsConfiguredWidget($page, 'adverts')
            ? $this->pageVisibilityResolver->getAdvertBlocksForPage($page, $siteId, $member)
            : [];

        $mainBlockCount = $pageBlocks
            ->filter(function ($b) use ($usedBlockIds) {
                $data = is_array($b->data) ? $b->data : json_decode($b->data, true);
                return !in_array($b->id, $usedBlockIds) && ($data['context'] ?? 'default') !== 'sidebar';
            })
            ->count();

        $minGap = $siteId ? $this->pageVisibilityResolver->minContentBlocksBetween() : PHP_INT_MAX;

        if ($mainBlockCount > 12) {
            $minGap = 4;
        }

        $maxInlineAdverts = (int) floor($mainBlockCount / ($minGap + 1));

        $advertIndex = 0;
        $sinceLastAdvert = 0;
        $inlineInjected = 0;

        foreach ($pageBlocks as $index => $block) {
            if (in_array($block->id, $usedBlockIds)) {
                continue;
            }

            try {
                foreach ($pageGrids as $pageGrid) {
                    if (!empty($pageGrid) && $pageGrid->order === ($index + 1)) {
                        $mainHtml .= $this->pageGridRenderer->render($pageGrid);
                    }
                }

                $data = is_array($block->data) ? $block->data : json_decode($block->data, true);
                $context = $data['context'] ?? 'default';

                $blockHtml = $this->blockParserService->buildBlock(
                    $block->page_id,
                    array_merge($data, ['type' => $block->type]),
                    $block->order,
                    false,
                    $siteId
                );

                if ($context === 'sidebar') {
                    $sidebarHtml .= $blockHtml;
                } else {
                    $mainHtml .= $blockHtml;
                    $sinceLastAdvert++;

                    if (
                        $inlineInjected < $maxInlineAdverts
                        && $advertIndex < count($advertBlocks)
                        && $sinceLastAdvert >= $minGap
                    ) {
                        $mainHtml .= $advertBlocks[$advertIndex];
                        $advertIndex++;
                        $inlineInjected++;
                        $sinceLastAdvert = 0;
                    }
                }
            } catch (\Exception $e) {
                error_log("Failed to render block {$block->id}: {$e->getMessage()}");
            }
        }

        $remaining = array_slice($advertBlocks, $advertIndex);

        if (count($remaining) === 1) {
            $mainHtml .= $remaining[0];
        } elseif (count($remaining) > 1) {
            $mainHtml .= '<div class="advert-overflow-row">';
            foreach ($remaining as $advertHtml) {
                $mainHtml .= $advertHtml;
            }
            $mainHtml .= '</div>';
        }

        $mainHtml .= $zonesResult['html'];

        return [
            'main' => $mainHtml,
            'sidebar' => $sidebarHtml,
            'hasSidebar' => !empty($sidebarHtml),
        ];
    }

    private function supportsConfiguredWidget(Page $page, string $widgetKey): bool
    {
        $pageTypes = config("public_content.widgets.{$widgetKey}.page_types", ['*']);

        if (!is_array($pageTypes)) {
            return true;
        }

        return in_array('*', $pageTypes, true)
            || in_array((string) $page->page_type, $pageTypes, true);
    }
}
