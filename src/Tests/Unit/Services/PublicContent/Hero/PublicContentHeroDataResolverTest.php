<?php

namespace App\Tests\Unit\Services\PublicContent\Hero;

use App\Enums\PublicContent\PageHeroType;
use App\Models\Image;
use App\Models\Page;
use App\Repositories\Cms\ImageRepository;
use App\Services\PublicContent\Hero\PublicContentHeroData;
use App\Services\PublicContent\Hero\PublicContentHeroDataResolver;
use App\Services\PublicContent\Images\PublicContentImageUrlResolver;
use App\Services\PublicContent\Images\PublicContentImageUrlSigner;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentHeroDataResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_resolves_a_video_hero_when_type_and_url_are_present(): void
    {
        $images = Mockery::mock(ImageRepository::class);
        $images->shouldReceive('find')->never();

        $resolver = new PublicContentHeroDataResolver($images, new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()));

        $result = $resolver->resolve($this->page(
            heroType: 'video',
            heroVideoUrl: 'https://example.test/hero.mp4',
            title: 'A video story',
        ));

        self::assertInstanceOf(PublicContentHeroData::class, $result);
        self::assertSame(PageHeroType::Video, $result->type);
        self::assertSame('https://example.test/hero.mp4', $result->videoUrl);
        self::assertNull($result->imageUrl);
        self::assertSame('A video story', $result->title);
    }

    public function test_it_falls_through_to_image_when_video_type_has_no_url(): void
    {
        $image = Mockery::mock(Image::class)->makePartial();
        $image->url = 'https://example.test/hero.jpg';

        $images = Mockery::mock(ImageRepository::class);
        $images->shouldReceive('find')->once()->with(9)->andReturn($image);

        $resolver = new PublicContentHeroDataResolver($images, new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()));

        $result = $resolver->resolve($this->page(
            heroType: 'video',
            heroVideoUrl: '',
            heroImageId: 9,
            siteId: 1,
        ));

        self::assertSame(PageHeroType::Image, $result->type);
        // A non-locally-managed URL passes through the resolver unchanged.
        self::assertSame('https://example.test/hero.jpg', $result->imageUrl);
    }

    public function test_it_resolves_an_image_hero(): void
    {
        $image = Mockery::mock(Image::class)->makePartial();
        $image->url = '/storage/uploads/hero.jpg';

        $images = Mockery::mock(ImageRepository::class);
        $images->shouldReceive('find')->once()->with(4)->andReturn($image);

        $resolver = new PublicContentHeroDataResolver($images, new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()));

        $result = $resolver->resolve($this->page(heroType: 'image', heroImageId: 4, siteId: 2));

        self::assertSame(PageHeroType::Image, $result->type);
        // A locally-managed image URL is rewritten to a signed public path.
        self::assertStringStartsWith('/public/images/', $result->imageUrl);
        self::assertNull($result->videoUrl);
    }

    public function test_it_returns_null_when_there_is_no_hero_image_id(): void
    {
        $images = Mockery::mock(ImageRepository::class);
        $images->shouldReceive('find')->never();

        $resolver = new PublicContentHeroDataResolver($images, new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()));

        self::assertNull($resolver->resolve($this->page(heroType: 'image', heroImageId: null)));
    }

    public function test_it_defaults_hero_title_position_to_standard_when_blank(): void
    {
        $images = Mockery::mock(ImageRepository::class);

        $resolver = new PublicContentHeroDataResolver($images, new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()));

        $result = $resolver->resolve($this->page(heroType: 'video', heroVideoUrl: 'https://x.test/v.mp4', heroTitlePosition: '   '));

        self::assertSame('standard', $result->heroTitlePosition);
    }

    public function test_it_preserves_a_custom_hero_title_position(): void
    {
        $images = Mockery::mock(ImageRepository::class);

        $resolver = new PublicContentHeroDataResolver($images, new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()));

        $result = $resolver->resolve($this->page(heroType: 'video', heroVideoUrl: 'https://x.test/v.mp4', heroTitlePosition: 'overlay'));

        self::assertSame('overlay', $result->heroTitlePosition);
    }

    private function page(
        string $heroType,
        ?string $heroVideoUrl = null,
        ?int $heroImageId = null,
        string $title = 'Title',
        string $heroTitlePosition = 'standard',
        int $siteId = 1,
    ): Page {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->hero_type = $heroType;
        $page->hero_video_url = $heroVideoUrl;
        $page->hero_image_id = $heroImageId;
        $page->title = $title;
        $page->hero_title_position = $heroTitlePosition;
        $page->site_id = $siteId;

        return $page;
    }
}