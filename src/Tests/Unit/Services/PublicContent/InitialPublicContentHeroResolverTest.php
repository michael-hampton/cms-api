<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\DTO\PublicContent\InitialPublicContentHero;
use App\Enums\PublicContent\PageHeroType;
use App\Framework\Support\Collection;
use App\Framework\View\ViewRenderer;
use App\Models\Page;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Hero\PublicContentHeroData;
use App\Services\PublicContent\Hero\PublicContentHeroDataResolver;
use App\Services\PublicContent\InitialPublicContentHeroResolver;
use App\Services\PublicContent\Widgets\PublicContentWidgetEligibility;
use Mockery;
use PHPUnit\Framework\TestCase;

final class InitialPublicContentHeroResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_null_when_there_is_no_hero(): void
    {
        $heroData = Mockery::mock(PublicContentHeroDataResolver::class);
        $heroData->shouldReceive('resolve')->once()->andReturn(null);

        $eligibility = new PublicContentWidgetEligibility(Mockery::mock(PublicContentConfigSource::class));

        $resolver = new InitialPublicContentHeroResolver($heroData, Mockery::mock(ViewRenderer::class), $eligibility);

        self::assertNull($resolver->resolve($this->page()));
    }

    public function test_it_returns_null_when_the_hero_block_widget_is_not_eligible_for_the_page_type(): void
    {
        $hero = new PublicContentHeroData(
            type: PageHeroType::Image,
            imageUrl: 'https://cdn.test/hero.jpg',
            videoUrl: null,
            title: 'Title',
            heroTitlePosition: 'standard',
        );

        $heroData = Mockery::mock(PublicContentHeroDataResolver::class);
        $heroData->shouldReceive('resolve')->once()->andReturn($hero);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->with(1, 'widgets.hero-block.page_types', ['*'])
            ->andReturn(['landing-page']);

        $eligibility = new PublicContentWidgetEligibility($config);

        $resolver = new InitialPublicContentHeroResolver($heroData, Mockery::mock(ViewRenderer::class), $eligibility);

        self::assertNull($resolver->resolve($this->page(pageType: 'article')));
    }

    public function test_it_renders_the_hero_block_when_eligible(): void
    {
        $hero = new PublicContentHeroData(
            type: PageHeroType::Image,
            imageUrl: 'https://cdn.test/hero.jpg',
            videoUrl: null,
            title: 'A great article',
            heroTitlePosition: 'standard',
        );

        $heroData = Mockery::mock(PublicContentHeroDataResolver::class);
        $heroData->shouldReceive('resolve')->once()->andReturn($hero);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->with(1, 'widgets.hero-block.page_types', ['*'])
            ->andReturn(['*']);

        $eligibility = new PublicContentWidgetEligibility($config);

        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('render')
            ->once()
            ->with('components/hero-block', $hero->toArray())
            ->andReturn('<div>hero</div>');

        $resolver = new InitialPublicContentHeroResolver($heroData, $views, $eligibility);

        $result = $resolver->resolve($this->page(id: 42, pageType: 'article'));

        self::assertInstanceOf(InitialPublicContentHero::class, $result);
        self::assertSame(42, $result->blockId);
        self::assertSame('<div>hero</div>', $result->html);
        self::assertSame('https://cdn.test/hero.jpg', $result->preloadUrl);
    }

    private function page(int $id = 1, string $pageType = 'article', int $siteId = 1): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = $id;
        $page->page_type = $pageType;
        $page->site_id = $siteId;
        $page->products = new Collection();

        return $page;
    }
}