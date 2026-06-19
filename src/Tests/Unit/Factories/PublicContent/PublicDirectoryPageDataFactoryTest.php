<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factories\PublicContent;

use App\Factories\PublicContent\PublicDirectoryPageDataFactory;
use App\Framework\Support\Collection;
use App\Services\Cms\Pages\PageCardImageResolver;
use PHPUnit\Framework\TestCase;

final class PublicDirectoryPageDataFactoryTest extends TestCase
{
    public function test_it_maps_page_relations_into_typed_directory_data(): void
    {
        $page = (object) [
            'id' => 42,
            'title' => 'Best guitar pedals',
            'slug' => 'best-guitar-pedals',
            'meta_description' => 'A complete guide to the best guitar pedals.',
            'published_at' => '2026-06-19 10:30:00',
            'created_at' => '2026-06-18 10:30:00',
            'categories' => new Collection([
                (object) ['name' => 'Reviews', 'slug' => 'reviews'],
            ]),
            'tags' => new Collection([
                (object) ['name' => 'Pedals', 'slug' => 'pedals'],
            ]),
            'authors' => new Collection([
                (object) ['name' => 'Phil Weller', 'slug' => 'phil-weller'],
            ]),
            'crop_overrides' => [],
            'resolved_images' => [
                'listing-card' => [
                    'image_url' => '/images/pedals.jpg',
                    'width' => 1200,
                    'height' => 675,
                ],
            ],
            'listing_use_as_hero' => false,
            'listing_image_id' => null,
            'hero_image_id' => null,
            'image' => null,
            'blocks' => [],
        ];

        $data = (new PublicDirectoryPageDataFactory(new PageCardImageResolver()))->make($page);

        self::assertSame(42, $data->id);
        self::assertSame('Best guitar pedals', $data->title);
        self::assertSame('/images/pedals.jpg', $data->image?->url);
        self::assertSame(1200, $data->image?->width);
        self::assertSame('Reviews', $data->categories[0]->name);
        self::assertSame('reviews', $data->categories[0]->slug);
        self::assertSame('Pedals', $data->tags[0]->name);
        self::assertSame('pedals', $data->tags[0]->slug);
        self::assertSame('Phil Weller', $data->authors[0]->name);
        self::assertSame('phil-weller', $data->authors[0]->slug);
    }
}
