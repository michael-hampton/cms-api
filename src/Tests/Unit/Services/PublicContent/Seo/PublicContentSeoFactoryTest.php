<?php

namespace App\Tests\Unit\Services\PublicContent\Seo;

use App\Models\Page;
use App\Models\Territory;
use App\Services\PublicContent\Locale\PublicContentLocaleResolver;
use App\Services\PublicContent\Seo\PublicContentSeoFactory;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentSeoFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_emits_hreflang_alternates_and_locale(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->title = 'Story';
        $page->slug = 'story';
        $page->page_type = 'article';
        $page->seo = null;
        $page->meta_title = null;
        $page->meta_description = 'Desc';
        $page->listing_synopsis = null;
        $page->description = null;

        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->code = 'en-GB';
        $territory->slug = 'uk';

        $alt = Mockery::mock(Territory::class)->makePartial();
        $alt->code = 'en-US';
        $alt->slug = 'us';

        $seo = (new PublicContentSeoFactory(new PublicContentLocaleResolver()))->make(
            page: $page,
            siteSlug: 'brand',
            territory: $territory,
            alternateTerritories: [$territory, $alt],
        );

        self::assertSame('en-GB', $seo->locale);
        self::assertSame('GB', $seo->region);
        self::assertNotEmpty($seo->hreflangAlternates);
        self::assertSame('en-GB', $seo->hreflangAlternates[0]['hreflang']);

        $tags = array_column($seo->hreflangAlternates, 'hreflang');
        self::assertContains('en-US', $tags);
    }
}
