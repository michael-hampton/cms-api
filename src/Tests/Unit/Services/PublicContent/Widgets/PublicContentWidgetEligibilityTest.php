<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;
use App\Models\Page;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Widgets\PublicContentWidgetEligibility;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentWidgetEligibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_page_type_override_beats_configured_page_types(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->never();

        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'landing-page';
        $context = new PublicContentContext($page, 7, 'guitar-world', null, [], ['comments']);

        self::assertTrue((new PublicContentWidgetEligibility($config))->supportsWidget($context, 'comments'));
    }

    public function test_configured_page_types_still_apply_without_an_override(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->once()
            ->with(7, 'widgets.comments.page_types', ['*'])
            ->andReturn(['article']);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'landing-page';
        $context = new PublicContentContext($page, 7, 'guitar-world');

        self::assertFalse((new PublicContentWidgetEligibility($config))->supportsWidget($context, 'comments'));
    }
}
