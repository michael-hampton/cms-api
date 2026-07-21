<?php

namespace App\Tests\Unit\Services\PublicContent\Social;

use App\Models\Page;
use App\Models\PageSocial;
use App\Services\PublicContent\Social\PageSocialShareStateResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PageSocialShareStateResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_null_when_sharing_disabled(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->social = null;

        self::assertNull((new PageSocialShareStateResolver())->resolve($page, '/canonical'));
    }

    public function test_builds_share_state_from_page_social(): void
    {
        $social = Mockery::mock(PageSocial::class)->makePartial();
        $social->enable_sharing = true;
        $social->platforms = ['facebook', 'twitter'];
        $social->share_text = 'Hello';
        $social->share_hashtags = 'news';

        $page = Mockery::mock(Page::class)->makePartial();
        $page->title = 'Fallback';
        $page->social = $social;

        $state = (new PageSocialShareStateResolver())->resolve($page, 'https://example.com/story');

        self::assertNotNull($state);
        self::assertTrue($state->enableSharing);
        self::assertSame(['facebook', 'twitter'], $state->platforms);
        self::assertSame('Hello', $state->shareText);
        self::assertSame('https://example.com/story', $state->shareUrl);
    }
}
