<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\Data\PublicContent\PublicDirectoryEntityData;
use App\Data\PublicContent\PublicDirectoryPageData;
use App\Enums\PublicContent\PublicDirectoryType;
use App\Framework\Support\Collection;
use App\Services\PublicContent\Directory\PublicDirectoryPresenter;
use PHPUnit\Framework\TestCase;

final class PublicDirectoryPresenterTest extends TestCase
{
    public function testItBuildsCanonicalEntityAndPageLinks(): void
    {
        $presenter = new PublicDirectoryPresenter();

        $category = (object) [
            'id' => 5,
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Technology stories',
            'icon' => 'technology',
            'color' => '#000000',
            'parent_id' => null,
        ];

        $page = (object) [
            'id' => 9,
            'title' => 'Example article',
            'slug' => 'example-article',
            'meta_description' => 'Summary',
            'metadata' => (object) ['featured_image' => '/image.jpg'],
            'published_at' => null,
            'created_at' => null,
            'categories' => new Collection([$category]),
            'tags' => new Collection([(object) ['name' => 'PHP', 'slug' => 'php']]),
            'authors' => new Collection([(object) ['name' => 'Mike', 'slug' => 'mike']]),
        ];

        $entity = $presenter->entity(
            PublicDirectoryEntityData::fromEntity(PublicDirectoryType::Category, $category),
            'estate',
        );
        $pages = $presenter->pages(
            new Collection([PublicDirectoryPageData::fromPage($page)]),
            'estate',
        );

        self::assertSame('category', $entity['type']);
        self::assertSame('/estate/categories/technology', $entity['url']);
        self::assertSame('/estate/tags/php', $pages[0]['tags'][0]['url']);
        self::assertSame('/estate/authors/mike', $pages[0]['authors'][0]['url']);
    }
}
