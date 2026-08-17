<?php

namespace App\Tests\Unit\Services\PublicContent\Config;

use App\Services\PublicContent\Config\FilePublicContentConfigSource;
use PHPUnit\Framework\TestCase;

final class FilePublicContentConfigSourceTest extends TestCase
{
    public function test_has_is_always_true_since_the_file_source_is_universally_resolvable(): void
    {
        $source = new FilePublicContentConfigSource();

        self::assertTrue($source->has(1));
        self::assertTrue($source->has(999));
    }

    public function test_get_reads_from_the_public_content_config_file(): void
    {
        $source = new FilePublicContentConfigSource();

        self::assertSame(
            ['content', 'article', 'landing-page', 'review', 'buying-guide'],
            $source->get(1, 'page_types'),
        );
        self::assertSame(['landing-page'], $source->get(1, 'widgets.activity-feed.page_types'));
        self::assertSame(10, $source->get(1, 'widgets.activity-feed.limit'));
        self::assertSame(['landing-page'], $source->get(1, 'widgets.newsletter.page_types'));
        self::assertSame(['landing-page'], $source->get(1, 'widgets.guest-contributors.page_types'));
        self::assertSame(['landing-page'], $source->get(1, 'widgets.categories-widget.page_types'));
    }

    public function test_get_returns_the_default_for_a_missing_key(): void
    {
        $source = new FilePublicContentConfigSource();

        self::assertSame('fallback', $source->get(1, 'widgets.does-not-exist.page_types', 'fallback'));
        self::assertNull($source->get(1, 'widgets.does-not-exist.page_types'));
    }

    public function test_get_is_not_scoped_by_site_id(): void
    {
        $source = new FilePublicContentConfigSource();

        self::assertSame(
            $source->get(1, 'page_types'),
            $source->get(42, 'page_types'),
        );
    }
}