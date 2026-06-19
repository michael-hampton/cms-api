<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\PublicContent\Directory;

use App\Models\Site;
use App\Services\PublicContent\Directory\PublicDirectoryCardConfigProvider;
use PHPUnit\Framework\TestCase;

final class PublicDirectoryCardConfigProviderTest extends TestCase
{
    public function test_it_uses_defaults_when_site_has_no_directory_configuration(): void
    {
        $site = new Site();
        $site->settings = [];

        $config = (new PublicDirectoryCardConfigProvider())->forSite($site);

        self::assertTrue($config->showImage);
        self::assertTrue($config->showSummary);
        self::assertTrue($config->showCategories);
        self::assertTrue($config->showTags);
        self::assertTrue($config->showAuthors);
        self::assertTrue($config->showPublishedDate);
        self::assertSame(2, $config->categoryLimit);
        self::assertSame(3, $config->tagLimit);
        self::assertSame(3, $config->authorLimit);
        self::assertSame(150, $config->summaryLength);
    }

    public function test_it_applies_valid_site_overrides_and_rejects_invalid_limits(): void
    {
        $site = new Site();
        $site->settings = [
            'public_directory' => [
                'page_card' => [
                    'show_tags' => false,
                    'show_summary' => false,
                    'category_limit' => 4,
                    'tag_limit' => 0,
                    'author_limit' => '2',
                    'summary_length' => -10,
                ],
            ],
        ];

        $config = (new PublicDirectoryCardConfigProvider())->forSite($site);

        self::assertFalse($config->showTags);
        self::assertFalse($config->showSummary);
        self::assertSame(4, $config->categoryLimit);
        self::assertSame(3, $config->tagLimit);
        self::assertSame(2, $config->authorLimit);
        self::assertSame(150, $config->summaryLength);
    }
}
