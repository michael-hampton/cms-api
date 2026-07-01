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
    ) {
    }

    public function resolve(Page $page): ?InitialPublicContentHero
    {
        $hero = $this->heroData->resolve($page);

        if ($hero === null || !$this->eligibility->supportsWidget(new PublicContentContext(page: $page, siteId: $page->site_id, siteSlug: 'test', viewData: []), 'hero-block')) {
            return null;
        }

        return new InitialPublicContentHero(
            blockId: (int) $page->id,
            html: $this->views->render(self::TEMPLATE, $hero->toArray()),
            preloadUrl: $hero->preloadUrl(),
        );
    }
}