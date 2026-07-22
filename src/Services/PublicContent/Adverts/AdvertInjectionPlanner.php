<?php

namespace App\Services\PublicContent\Adverts;

use App\DTO\PublicContent\Adverts\AdvertInjectionPlan;
use App\DTO\PublicContent\Adverts\AdvertSlot;
use App\DTO\PublicContent\Sources\SourceResult;
use App\Enums\PublicContent\AdvertFrequency;
use App\Models\Member;
use App\Models\Page;
use App\Services\Adverts\PageVisibilityResolver;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use Throwable;

/**
 * Plans density-scattered advert injection for main content.
 * Spacing comes from widgets.adverts.frequency (less | balanced | more).
 */
final class AdvertInjectionPlanner
{
    private const int LONG_PAGE_BLOCK_THRESHOLD = 12;

    public function __construct(
        private readonly PageVisibilityResolver $visibility,
        private readonly PublicContentConfigSource $publicContentConfig,
    ) {
    }

    /**
     * @param list<object> $mainBlocks ordered main (non-sidebar, non-zone) blocks
     * @param list<string>|null $advertHtmlBlocks pre-rendered advert HTML; null loads from visibility resolver
     */
    public function plan(
        Page $page,
        int $siteId,
        array $mainBlocks,
        ?Member $member = null,
        ?array $advertHtmlBlocks = null,
    ): AdvertInjectionPlan {
        if (!$this->supportsConfiguredWidget($page, $siteId, 'adverts')) {
            return AdvertInjectionPlan::none();
        }

        try {
            $advertHtmlBlocks ??= $this->visibility->getAdvertBlocksForPage($page, $siteId, $member);
        } catch (Throwable) {
            return new AdvertInjectionPlan(
                inlineHtmlByMainBlockIndex: [],
                overflowHtml: [],
                slots: [],
                source: SourceResult::degraded('unavailable'),
                mainBlockCount: count($mainBlocks),
                minGap: 0,
                maxInlineAdverts: 0,
            );
        }

        if ($advertHtmlBlocks === []) {
            return new AdvertInjectionPlan(
                inlineHtmlByMainBlockIndex: [],
                overflowHtml: [],
                slots: [],
                source: SourceResult::empty(),
                mainBlockCount: count($mainBlocks),
                minGap: 0,
                maxInlineAdverts: 0,
            );
        }

        $mainBlockCount = count($mainBlocks);
        $frequency = AdvertFrequency::tryFromConfig(
            $this->publicContentConfig->get($siteId, 'widgets.adverts.frequency', AdvertFrequency::Balanced->value),
        );
        $minGap = $mainBlockCount > self::LONG_PAGE_BLOCK_THRESHOLD
            ? $frequency->longPageBlocksBetweenAds()
            : $frequency->blocksBetweenAds();

        $maxInlineAdverts = (int) floor($mainBlockCount / ($minGap + 1));
        $inline = [];
        $slots = [];
        $advertIndex = 0;
        $sinceLastAdvert = 0;
        $inlineInjected = 0;

        for ($ordinal = 1; $ordinal <= $mainBlockCount; $ordinal++) {
            $sinceLastAdvert++;

            if (
                $inlineInjected < $maxInlineAdverts
                && $advertIndex < count($advertHtmlBlocks)
                && $sinceLastAdvert >= $minGap
            ) {
                $html = $advertHtmlBlocks[$advertIndex];
                $inline[$ordinal] = $html;
                $slots[] = new AdvertSlot(
                    index: count($slots),
                    placement: 'inline',
                    afterMainBlockIndex: $ordinal,
                    type: $this->typeFromHtml($html),
                    payload: ['html_present' => true],
                );
                $advertIndex++;
                $inlineInjected++;
                $sinceLastAdvert = 0;
            }
        }

        $overflow = array_slice($advertHtmlBlocks, $advertIndex);
        foreach ($overflow as $html) {
            $slots[] = new AdvertSlot(
                index: count($slots),
                placement: 'overflow',
                afterMainBlockIndex: null,
                type: $this->typeFromHtml($html),
                payload: ['html_present' => true],
            );
        }

        return new AdvertInjectionPlan(
            inlineHtmlByMainBlockIndex: $inline,
            overflowHtml: $overflow,
            slots: $slots,
            source: SourceResult::ok($slots),
            mainBlockCount: $mainBlockCount,
            minGap: $minGap,
            maxInlineAdverts: $maxInlineAdverts,
        );
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

    private function typeFromHtml(string $html): string
    {
        if (preg_match('/data-type="([^"]+)"/', $html, $matches) === 1) {
            return $matches[1];
        }

        return 'advert';
    }
}
