<?php

namespace App\Services\Adverts;

use App\Models\Page;
use App\Parsers\Renderers\Page\PageBoostRenderer;
use App\Parsers\Renderers\Page\PageDealRenderer;
use App\Parsers\Renderers\Page\PageOfferRenderer;
use App\Parsers\Renderers\Page\PageRewardRenderer;
use App\Services\Adverts\Boost\BoostRankingService;

class PageVisibilityResolver
{
    private const MIN_CONTENT_BLOCKS_BETWEEN = 2;

    public function __construct(
        private readonly PromotionInjector   $promotionInjector,
        private readonly BoostRankingService $boostRankingService,
        private readonly PageOfferRenderer   $offerRenderer,
        private readonly PageDealRenderer    $dealRenderer,
        private readonly PageRewardRenderer  $rewardRenderer,
        private readonly PageBoostRenderer   $boostRenderer,
    )
    {
    }

    public function getAdvertBlocksForPage(Page $page, int $siteId, ?\App\Models\Member $member = null): array
    {
        $context = new RenderContext(
            memberId: $member?->id,
            plan: $page->title,
            isPaid: $member?->isPaid() ?? false,
            channel: 'web',
            surfaceType: 'page',
            surfaceId: $page->id,
            timestamp: now_datetime(),
        );

        $promotionBlocks = $this->promotionInjector->getBlocksForSurface(
            surfaceType: 'page',
            surfaceId: $page->id,
            member: $member,
            siteId: $siteId,
            channel: 'web',
        );

        $boostBlocks = $this->resolveBoostBlocks($page);
        $merged = $this->mergeAdvertBlocks($promotionBlocks, $boostBlocks);
        $rendered = $this->renderBlocks($merged, $this->buildRenderContext($page, $member));

        return $this->reorderForDiversity($rendered);
    }

    private function resolveBoostBlocks(Page $page): array
    {
        $blocks = [];
        $seen = [];

        foreach (['listing', 'deals', 'recommendations'] as $boostContext) {
            $ranked = $this->boostRankingService->getRankedBoosts($boostContext);

            foreach ($ranked as $entry) {
                $boost = $entry['boost'];

                // Deduplicate across contexts
                if (isset($seen[$boost->boostable_id])) {
                    continue;
                }
                $seen[$boost->boostable_id] = true;

                $blocks[] = [
                    'type' => 'boost',
                    'injection_type' => 'boost',
                    'priority' => (int)round($entry['rank_score'] * 100),
                    'data' => [
                        'boost_id' => $boost->id,
                        'boostable_type' => $boost->boostable_type,
                        'boostable_id' => $boost->boostable_id,
                        'context' => $boost->context,
                        'multiplier' => $boost->multiplier,
                    ],
                ];
            }
        }

        return $blocks;
    }

    private function mergeAdvertBlocks(array $promotionBlocks, array $boostBlocks): array
    {
        $promotedIds = [];
        foreach ($promotionBlocks as $block) {
            if (!empty($block['data']['product_id'])) {
                $promotedIds[] = $block['data']['product_id'];
            }
            if (!empty($block['data']['offer_id'])) {
                $promotedIds[] = $block['data']['offer_id'];
            }
        }

        $filteredBoosts = array_filter(
            $boostBlocks,
            fn($b) => !in_array($b['data']['boostable_id'] ?? null, $promotedIds, true)
        );

        return array_merge($promotionBlocks, array_values($filteredBoosts));
    }

    /**
     * Renders all advert blocks to HTML strings, skipping empty results.
     * Returns array of non-empty HTML strings ready for injection.
     */
    private function renderBlocks(array $blocks, RenderContext $context): array
    {
        $rendered = [];

        foreach ($blocks as $block) {
            $html = match ($block['type']) {
                'offer' => $this->offerRenderer->render((int)$block['data']['offer_id'], $context),
                'offer-deal' => $this->dealRenderer->render((int)$block['data']['product_id'], $context),
                'reward' => $this->rewardRenderer->render((int)$block['data']['reward_id'], $context),
                'boost' => $this->boostRenderer->render($block['data']),
                default => '',
            };

            if (!empty($html)) {
                $rendered[] = $html;
            }
        }

        return $rendered;
    }

    private function buildRenderContext(Page $page, ?\App\Models\Member $member): RenderContext
    {
        return new RenderContext(
            memberId: $member?->id,
            plan: $page->title,
            isPaid: $member?->isPaid() ?? false,
            channel: 'web',
            surfaceType: 'page',
            surfaceId: $page->id,
            timestamp: now_datetime(),
        );
    }

    /**
     * Reorders rendered advert blocks so:
     * 1. Boost blocks always come first (highest precedence)
     * 2. Remaining types are interleaved — never two of the same type consecutively
     */
    private function reorderForDiversity(array $blocks): array
    {
        // Separate boosts from everything else
        $boosts = [];
        $byType = [];

        foreach ($blocks as $block) {
            // Detect type from the data-advert attribute in the rendered HTML
            if (preg_match('/data-advert="([^"]+)"/', $block, $matches)) {
                $type = $matches[1];
                if ($type === 'boost') {
                    $boosts[] = $block;
                } else {
                    $byType[$type][] = $block;
                }
            } else {
                $byType['unknown'][] = $block;
            }
        }

        // Round-robin interleave non-boost types
        $interleaved = [];
        $pools = array_values(array_filter($byType));

        while (!empty($pools)) {
            foreach ($pools as $key => $pool) {
                $interleaved[] = array_shift($pools[$key]);
                if (empty($pools[$key])) {
                    unset($pools[$key]);
                }
            }
            $pools = array_values($pools); // re-index after unset
        }

        // Boosts first, then interleaved remainder
        return array_merge($boosts, $interleaved);
    }

    public function minContentBlocksBetween(): int
    {
        return self::MIN_CONTENT_BLOCKS_BETWEEN;
    }
}