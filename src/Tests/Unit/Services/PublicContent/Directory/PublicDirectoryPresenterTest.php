<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\PublicContent\Directory;

use App\Data\PublicContent\PublicDirectoryPageCardConfigData;
use App\Data\PublicContent\PublicDirectoryPageData;
use App\Data\PublicContent\PublicDirectoryPageImageData;
use App\Data\PublicContent\PublicDirectoryRelationData;
use App\Framework\Support\Collection;
use App\Services\PublicContent\Directory\PublicDirectoryPresenter;
use PHPUnit\Framework\TestCase;

final class PublicDirectoryPresenterTest extends TestCase
{
    public function test_it_presents_full_page_card_data_with_canonical_relation_urls(): void
    {
        $page = new PublicDirectoryPageData(
            id: 42,
            title: 'Best guitar pedals',
            slug: 'best-guitar-pedals',
            summary: 'A complete guide.',
            image: new PublicDirectoryPageImageData('/images/pedals.jpg', 1200, 675, 'Best guitar pedals'),
            publishedAt: '2026-06-19T10:30:00+00:00',
            categories: [new PublicDirectoryRelationData('Reviews', 'reviews')],
            tags: [new PublicDirectoryRelationData('Pedals', 'pedals')],
            authors: [new PublicDirectoryRelationData('Phil Weller', 'phil-weller')],
        );

        $result = (new PublicDirectoryPresenter())
            ->pages(new Collection([$page]), 'guitar-world')[0];

        self::assertSame('/guitar-world/best-guitar-pedals', $result['url']);
        self::assertSame('/images/pedals.jpg', $result['image']['url']);
        self::assertSame('/guitar-world/categories/reviews', $result['categories'][0]['url']);
        self::assertSame('/guitar-world/tags/pedals', $result['tags'][0]['url']);
        self::assertSame('/guitar-world/authors/phil-weller', $result['authors'][0]['url']);
    }

    public function test_it_presents_backend_page_card_configuration(): void
    {
        $config = new PublicDirectoryPageCardConfigData(
            showImage: true,
            showSummary: false,
            showCategories: true,
            showTags: false,
            showAuthors: true,
            showPublishedDate: true,
            categoryLimit: 2,
            tagLimit: 3,
            authorLimit: 1,
            summaryLength: 120,
        );

        $result = (new PublicDirectoryPresenter())->pageCardConfig($config);

        self::assertTrue($result['show_image']);
        self::assertFalse($result['show_summary']);
        self::assertTrue($result['show_categories']);
        self::assertFalse($result['show_tags']);
        self::assertSame(2, $result['category_limit']);
        self::assertSame(3, $result['tag_limit']);
        self::assertSame(1, $result['author_limit']);
        self::assertSame(120, $result['summary_length']);
    }
}
