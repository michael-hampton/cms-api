<?php

namespace App\Services\Cms\Pages;

use App\Models\Member;
use App\Models\Page;
use App\Parsers\PageGridRenderer;
use App\Parsers\ZoneBlockParser;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\Pages\PageGridRepository;
use App\Services\Adverts\PageVisibilityResolver;
use App\Services\PublicContent\Config\PublicContentConfigSource;

class PageRenderService
{
    public function __construct(
        private readonly BlockRepository        $blockRepository,
        private readonly BlockParserService     $blockParserService,
        private readonly ZoneBlockParser        $zoneBlockParser,
        private readonly PageGridRepository     $pageGridRepository,
        private readonly PageVisibilityResolver $pageVisibilityResolver,
        private readonly PageGridRenderer       $pageGridRenderer,
        private readonly PublicContentConfigSource $publicContentConfig,
    ) {
    }

    /**
     * Renders a page with proper separation of main content and sidebar blocks.
     * Advert blocks (offers, deals, rewards, boosts) are interleaved into main content.
     * Returns an array with 'main', 'sidebar', and 'hasSidebar'.
     */
    public function renderPage(Page $page, ?int $siteId = null, ?Member $member = null): array
    {
        $mainHtml = '';
        $sidebarHtml = '';

        $zonesResult = $this->zoneBlockParser->buildZonesHtml($page);
        $usedBlockIds = $zonesResult['usedBlockIds'];
        $pageBlocks = $this->blockRepository->getPageBlocks($page->id);
        $pageGrids = $this->pageGridRepository->getActiveGridForPage($page->id);

        $advertBlocks = $siteId && $this->supportsConfiguredWidget($page, $siteId, 'adverts')
            ? $this->pageVisibilityResolver->getAdvertBlocksForPage($page, $siteId, $member)
            : [];

        // Calculate how many adverts can be injected inline based on available main content blocks
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

            if($block->type === 'hero' && $page->slug === 'home') { //todo
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

        // Remaining adverts — single leftover appended solo, multiple go into an inline flex row
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

    private function supportsConfiguredWidget(Page $page, int $siteId, string $widgetKey): bool
    {
        $pageTypes = $this->publicContentConfig->get($siteId, "widgets.{$widgetKey}.page_types", ['*']);

        if (!is_array($pageTypes)) {
            return true;
        }

        return in_array('*', $pageTypes, true)
            || in_array((string) $page->page_type, $pageTypes, true);
    }

    /**
     * Renders a single advert block as an HTML placeholder/wrapper.
     * The frontend (or a dedicated block renderer) is responsible for
     * the actual visual output — this emits a data-annotated div
     * that can be hydrated client-side or replaced server-side.
     */
    private function renderAdvertBlock(array $block): string
    {
        $type = htmlspecialchars($block['type'] ?? 'advert');
        $data = htmlspecialchars(json_encode($block['data'] ?? []), ENT_QUOTES);

        return sprintf(
            '<div class="advert-injection" data-type="%s" data-block="%s"></div>',
            $type,
            $data
        );
    }
}
