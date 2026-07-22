<?php

namespace App\Tests\Unit\Services\PublicContent\Layout;

use App\DTO\PublicContent\Inheritance\EffectivePublicContentPage;
use App\Enums\PublicContent\LayoutResolutionSource;
use App\Enums\PublicContent\LayoutResolutionStatus;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Layout\PublicContentLayoutPrecedenceResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentLayoutPrecedenceResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_page_template_wins(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->never();

        $result = (new PublicContentLayoutPrecedenceResolver($config))->resolve(
            new EffectivePublicContentPage(1, 7, ['template' => 'page-shell']),
        );

        self::assertTrue($result->isResolved());
        self::assertSame('page-shell', $result->template);
        self::assertSame(LayoutResolutionSource::PageSettings, $result->source);
    }

    public function test_site_catch_all_used_when_page_template_unset(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->once()
            ->with(7, 'layout.default_template', null)
            ->andReturn('site-shell');

        $result = (new PublicContentLayoutPrecedenceResolver($config))->resolve(
            new EffectivePublicContentPage(1, 7, ['template' => 'default-unset']),
        );

        self::assertTrue($result->isResolved());
        self::assertSame('site-shell', $result->template);
        self::assertSame(LayoutResolutionSource::SiteDefault, $result->source);
    }

    public function test_no_layout_resolved_when_nothing_set(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->once()
            ->with(7, 'layout.default_template', null)
            ->andReturn('');

        $result = (new PublicContentLayoutPrecedenceResolver($config))->resolve(
            new EffectivePublicContentPage(1, 7, ['template' => null]),
        );

        self::assertTrue($result->isNoLayoutResolved());
        self::assertSame(LayoutResolutionStatus::NoLayoutResolved, $result->status);
        self::assertNull($result->template);
        self::assertNull($result->source);
    }

    public function test_page_type_is_not_consulted_as_layout_fallback(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->once()
            ->with(7, 'layout.default_template', null)
            ->andReturn(null);

        $result = (new PublicContentLayoutPrecedenceResolver($config))->resolve(
            new EffectivePublicContentPage(1, 7, [
                'template' => '',
                // Intentionally present to assert the resolver never treats it as layout.
                'page_type' => 'article',
            ]),
        );

        self::assertTrue($result->isNoLayoutResolved());
        self::assertNotSame('article', $result->template);
    }
}
