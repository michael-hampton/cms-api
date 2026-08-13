<?php

namespace App\Tests\Unit\Services\PublicContent\Images;

use App\Framework\Support\Collection;
use App\Models\Image;
use App\Models\Page;
use App\Services\PublicContent\Images\PublicContentListingImageHydrator;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentListingImageHydratorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_stamps_resolved_images_from_eager_loaded_listing_image(): void
    {
        $image = Mockery::mock(Image::class)->makePartial();
        $image->url = 'https://cdn.example/a.jpg';
        $image->width = 800;
        $image->height = 600;

        $page = Mockery::mock(Page::class)->makePartial();
        $page->listing_image_id = 12;
        $page->setRelation('listingImage', $image);

        $hydrated = (new PublicContentListingImageHydrator())->hydrate(new Collection([$page]));
        $result = $hydrated->first();

        self::assertSame('https://cdn.example/a.jpg', $result->resolved_images['listing-card']['url']);
        self::assertSame(800, $result->resolved_images['listing-card']['width']);
    }
}
