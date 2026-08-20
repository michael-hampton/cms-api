<?php

namespace App\Services\PublicContent;

use App\DTO\PublicContent\InitialPublicContentHero;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;
use App\Models\Page;
use App\Services\PublicContent\Hero\PublicContentHeroDataResolver;
use App\Services\PublicContent\Widgets\PublicContentWidgetEligibility;

final readonly class InitialPublicContentHeroResolver
{
    private const string TEMPLATE = 'components/hero-block';

    public function __construct(
        private PublicContentHeroDataResolver $heroData,
        private ViewRenderer $views,
        private readonly PublicContentWidgetEligibility $eligibility,
        private readonly \App\Services\PublicContent\Widgets\PageWidgetDisablePolicy $pageWidgetDisables,
    ) {
    }

    public function resolve(Page $page): ?InitialPublicContentHero
    {
        $hero = $this->heroData->resolve($page);
        $siteId = (int) $page->site_id;
        $pageId = (int) $page->id;

        if (
            $hero === null
            || $this->pageWidgetDisables->isDisabled($siteId, $pageId, 'hero-block')
            || !$this->eligibility->supportsWidget(new PublicContentContext(page: $page, siteId: $siteId, siteSlug: 'test', viewData: []), 'hero-block')
        ) {
            return null;
        }

        return new InitialPublicContentHero(
            blockId: (int) $page->id,
            html: $this->views->render(self::TEMPLATE, $hero->toArray()),
            preloadUrl: $hero->preloadUrl(),
        );
    }
}