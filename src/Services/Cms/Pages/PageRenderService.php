<?php

namespace App\Services\Cms\Pages;

use App\Models\Member;
use App\Models\Page;
use App\Parsers\PageGridRenderer;
use App\Parsers\ZoneBlockParser;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\Pages\PageGridRepository;
use App\Services\Adverts\PageVisibilityResolver;
use App\Services\PublicContent\Adverts\AdvertInjectionPlanner;
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
        private readonly ?AdvertInjectionPlanner $advertPlanner = null,
    ) {
    }

    /**
     * Renders a page with proper separation of main content and sidebar blocks.
     * Advert blocks (offers, deals, rewards, boosts) are interleaved into main content.
     * Returns an array with 'main', 'sidebar', 'hasSidebar', and 'adverts' (structured plan).
     */
    public function renderPage(Page $page, ?int $siteId = null, ?Member $member = null): array
    {
        $mainHtml = '';
        $sidebarHtml = '';

        $zonesResult = $this->zoneBlockParser->buildZonesHtml($page);
        $usedBlockIds = $zonesResult['usedBlockIds'];
        $pageBlocks = $this->blockRepository->getPageBlocks($page->id);
        $pageGrids = $this->pageGridRepository->getActiveGridForPage($page->id);

        $mainBlocks = [];
        foreach ($pageBlocks as $block) {
            if (in_array($block->id, $usedBlockIds, true)) {
                continue;
            }
            if ($block->type === 'hero' && $page->slug === 'home') {
                continue;
            }
            $data = is_array($block->data) ? $block->data : json_decode($block->data, true);
            if (($data['context'] ?? 'default') === 'sidebar') {
                continue;
            }
            $mainBlocks[] = $block;
        }

        $advertPlan = null;
        $inlineByOrdinal = [];
        $overflowHtml = [];

        if ($siteId && $this->advertPlanner !== null) {
            $advertPlan = $this->advertPlanner->plan($page, $siteId, $mainBlocks, $member);
            $inlineByOrdinal = $advertPlan->inlineHtmlByMainBlockIndex;
            $overflowHtml = $advertPlan->overflowHtml;
        } elseif ($siteId && $this->supportsConfiguredWidget($page, $siteId, 'adverts')) {
            $advertBlocks = $this->pageVisibilityResolver->getAdvertBlocksForPage($page, $siteId, $member);
            $mainBlockCount = count($mainBlocks);
            $frequency = \App\Enums\PublicContent\AdvertFrequency::tryFromConfig(
                $this->publicContentConfig->get($siteId, 'widgets.adverts.frequency', 'balanced'),
            );
            $minGap = $mainBlockCount > 12
                ? $frequency->longPageBlocksBetweenAds()
                : $frequency->blocksBetweenAds();
            $maxInlineAdverts = (int) floor($mainBlockCount / ($minGap + 1));
            $advertIndex = 0;
            $sinceLastAdvert = 0;
            $inlineInjected = 0;
            $ordinal = 0;
            foreach ($mainBlocks as $_) {
                $ordinal++;
                $sinceLastAdvert++;
                if (
                    $inlineInjected < $maxInlineAdverts
                    && $advertIndex < count($advertBlocks)
                    && $sinceLastAdvert >= $minGap
                ) {
                    $inlineByOrdinal[$ordinal] = $advertBlocks[$advertIndex];
                    $advertIndex++;
                    $inlineInjected++;
                    $sinceLastAdvert = 0;
                }
            }
            $overflowHtml = array_slice($advertBlocks, $advertIndex);
        }

        $mainOrdinal = 0;

        foreach ($pageBlocks as $index => $block) {
            if (in_array($block->id, $usedBlockIds)) {
                continue;
            }

            if ($block->type === 'hero' && $page->slug === 'home') {
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
                    $mainOrdinal++;
                    if (isset($inlineByOrdinal[$mainOrdinal])) {
                        $mainHtml .= $inlineByOrdinal[$mainOrdinal];
                    }
                }
            } catch (\Exception $e) {
                error_log("Failed to render block {$block->id}: {$e->getMessage()}");
            }
        }

        if (count($overflowHtml) === 1) {
            $mainHtml .= $overflowHtml[0];
        } elseif (count($overflowHtml) > 1) {
            $mainHtml .= '<div class="advert-overflow-row">';
            foreach ($overflowHtml as $advertHtml) {
                $mainHtml .= $advertHtml;
            }
            $mainHtml .= '</div>';
        }

        $mainHtml .= $zonesResult['html'];

        return [
            'main' => $mainHtml,
            'sidebar' => $sidebarHtml,
            'hasSidebar' => !empty($sidebarHtml),
            'adverts' => $advertPlan?->toDocumentArray() ?? [
                'status' => 'empty',
                'reason' => null,
                'main_block_count' => count($mainBlocks),
                'min_gap' => 0,
                'max_inline_adverts' => 0,
                'slots' => [],
            ],
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
