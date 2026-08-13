<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\PublicContent;

use App\Data\PublicContent\PublicDirectoryEntityData;
use App\Data\PublicContent\PublicDirectoryPageData;
use App\Data\PublicContent\PublicDirectoryPageImageData;
use App\Data\PublicContent\PublicDirectoryRelationData;
use App\Enums\PublicContent\PublicDirectoryType;
use App\Framework\Support\Collection;
use App\Services\PublicContent\Directory\PublicDirectoryPresenter;
use App\Services\PublicContent\Images\PublicContentImageUrlResolver;
use App\Services\PublicContent\Images\PublicContentImageUrlSigner;
use App\Services\PublicContent\Images\PublicContentImageUrlTransformer;
use PHPUnit\Framework\TestCase;

final class PublicDirectoryPresenterTest extends TestCase
{
    public function testItBuildsCanonicalEntityAndPageLinks(): void
    {
        $presenter = new PublicDirectoryPresenter($this->imageUrls());

        $category = (object) [
            'id' => 5,
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Technology stories',
            'icon' => 'technology',
            'color' => '#000000',
            'parent_id' => null,
        ];

        $page = new PublicDirectoryPageData(
            id: 9,
            title: 'Example article',
            slug: 'example-article',
            summary: 'Summary',
            image: new PublicDirectoryPageImageData(
                url: '/image.jpg',
                width: null,
                height: null,
                alt: 'Example article',
            ),
            publishedAt: null,
            categories: [new PublicDirectoryRelationData('Technology', 'technology')],
            tags: [new PublicDirectoryRelationData('PHP', 'php')],
            authors: [new PublicDirectoryRelationData('Mike', 'mike')],
        );

        $entity = $presenter->entity(
            PublicDirectoryEntityData::fromEntity(PublicDirectoryType::Category, $category),
            'estate',
        );
        $pages = $presenter->pages(new Collection([$page]), 'estate');

        self::assertSame('category', $entity['type']);
        self::assertSame('/estate/categories/technology', $entity['url']);
        self::assertSame('/estate/tags/php', $pages[0]['tags'][0]['url']);
        self::assertSame('/estate/authors/mike', $pages[0]['authors'][0]['url']);
    }

    public function test_rewrites_page_and_entity_images_to_public_urls(): void
    {
        $presenter = new PublicDirectoryPresenter($this->imageUrls());

        $pages = $presenter->pages(new Collection([
            new PublicDirectoryPageData(
                id: 1,
                title: 'Article',
                slug: 'article',
                summary: null,
                image: new PublicDirectoryPageImageData(
                    url: '/storage/uploads/images/card.jpg',
                    width: 800,
                    height: 600,
                    alt: 'Article',
                ),
                publishedAt: null,
                categories: [],
                tags: [],
                authors: [],
            ),
        ]), 'guitar-world');

        $author = (object) [
            'id' => 2,
            'name' => 'Jonathan',
            'slug' => 'jonathan-horsley',
            'bio' => null,
            'avatar' => '/uploads/avatars/author.jpg',
        ];
        $entity = $presenter->entity(
            PublicDirectoryEntityData::fromEntity(PublicDirectoryType::Author, $author),
            'guitar-world',
        );

        self::assertStringStartsWith('/public/images/', $pages[0]['image']['url']);
        self::assertStringStartsWith('/public/images/', $entity['image']);
        self::assertStringNotContainsString('/storage/uploads/', $pages[0]['image']['url']);
        self::assertStringNotContainsString('/uploads/avatars/', $entity['image']);
    }

    private function imageUrls(): PublicContentImageUrlTransformer
    {
        return new PublicContentImageUrlTransformer(
            new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()),
        );
    }
}
