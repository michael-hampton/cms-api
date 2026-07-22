<?php

namespace App\Tests\Unit\Services\PublicContent\Adverts;

use App\Enums\PublicContent\AdvertFrequency;
use App\Models\Page;
use App\Services\Adverts\PageVisibilityResolver;
use App\Services\PublicContent\Adverts\AdvertInjectionPlanner;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use Mockery;
use PHPUnit\Framework\TestCase;

final class AdvertInjectionPlannerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_balanced_frequency_uses_long_page_gap_of_three(): void
    {
        $planner = $this->planner('balanced');
        $page = $this->articlePage();

        $few = $planner->plan($page, 1, array_fill(0, 4, (object) ['id' => 1]));
        $many = $planner->plan($page, 1, array_fill(0, 20, (object) ['id' => 1]));

        self::assertSame(2, $few->minGap);
        self::assertSame(1, $few->maxInlineAdverts);
        self::assertSame(3, $many->minGap);
        self::assertSame(5, $many->maxInlineAdverts);
        self::assertCount(5, $many->inlineHtmlByMainBlockIndex);
        self::assertSame('ok', $many->toDocumentArray()['status']);
    }

    public function test_more_frequency_increases_long_page_ads(): void
    {
        $planner = $this->planner('more');
        $page = $this->articlePage();

        $many = $planner->plan($page, 1, array_fill(0, 20, (object) ['id' => 1]));

        self::assertSame(2, $many->minGap);
        self::assertSame(6, $many->maxInlineAdverts);
        self::assertCount(6, $many->inlineHtmlByMainBlockIndex);
    }

    public function test_less_frequency_spaces_ads_further_apart(): void
    {
        $planner = $this->planner('less');
        $page = $this->articlePage();

        $many = $planner->plan($page, 1, array_fill(0, 20, (object) ['id' => 1]));

        self::assertSame(4, $many->minGap);
        self::assertSame(4, $many->maxInlineAdverts);
        self::assertCount(4, $many->inlineHtmlByMainBlockIndex);
    }

    public function test_ineligible_page_type_returns_empty_plan(): void
    {
        $visibility = Mockery::mock(PageVisibilityResolver::class);
        $visibility->shouldReceive('getAdvertBlocksForPage')->never();

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->with(1, 'widgets.adverts.page_types', ['*'])
            ->andReturn(['article']);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'review';

        $plan = (new AdvertInjectionPlanner($visibility, $config))->plan($page, 1, [(object) ['id' => 1]]);

        self::assertSame([], $plan->slots);
        self::assertSame('empty', $plan->toDocumentArray()['status']);
    }

    public function test_advert_frequency_labels_are_user_facing(): void
    {
        self::assertSame('Balanced', AdvertFrequency::Balanced->label());
        self::assertNotSame('', AdvertFrequency::More->description());
    }

    private function planner(string $frequency): AdvertInjectionPlanner
    {
        $visibility = Mockery::mock(PageVisibilityResolver::class);
        $visibility->shouldReceive('getAdvertBlocksForPage')->andReturn([
            '<div class="advert-injection" data-type="offer" data-block="{}"></div>',
            '<div class="advert-injection" data-type="offer" data-block="{}"></div>',
            '<div class="advert-injection" data-type="offer" data-block="{}"></div>',
            '<div class="advert-injection" data-type="offer" data-block="{}"></div>',
            '<div class="advert-injection" data-type="offer" data-block="{}"></div>',
            '<div class="advert-injection" data-type="offer" data-block="{}"></div>',
        ]);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->with(1, 'widgets.adverts.page_types', ['*'])
            ->andReturn(['article']);
        $config->shouldReceive('get')->with(1, 'widgets.adverts.frequency', AdvertFrequency::Balanced->value)
            ->andReturn($frequency);

        return new AdvertInjectionPlanner($visibility, $config);
    }

    private function articlePage(): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'article';

        return $page;
    }
}
