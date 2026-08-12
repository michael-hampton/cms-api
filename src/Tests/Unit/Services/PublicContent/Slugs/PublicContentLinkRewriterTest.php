<?php

namespace App\Tests\Unit\Services\PublicContent\Slugs;

use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Slugs\PublicContentLinkRewriter;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentLinkRewriterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_regional_pass_prefixes_internal_links_with_territory(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->slug = 'story';

        $paths = Mockery::mock(PublicContentPathResolver::class);
        $paths->shouldReceive('canonicalPathForPage')->once()->with($page)->andReturn('story');

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findPublishedBySlugs')
            ->once()
            ->with(1, ['story'], ['categories'])
            ->andReturn(['story' => $page]);

        $html = (new PublicContentLinkRewriter($paths, $pages))->rewriteHtml(
            '<p><a href="/brand/story">Next</a></p>',
            1,
            'brand',
            'uk',
        );

        self::assertSame('<p><a href="/brand/uk/story">Next</a></p>', $html);
    }

    public function test_regional_pass_keeps_already_regional_links_and_canonicalises(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->slug = 'story';

        $paths = Mockery::mock(PublicContentPathResolver::class);
        $paths->shouldReceive('canonicalPathForPage')->once()->with($page)->andReturn('guides/story');

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findPublishedBySlugs')
            ->once()
            ->with(1, ['story'], ['categories'])
            ->andReturn(['story' => $page]);

        $html = (new PublicContentLinkRewriter($paths, $pages))->rewriteHtml(
            '<a href="/brand/uk/story">Next</a>',
            1,
            'brand',
            'uk',
        );

        self::assertSame('<a href="/brand/uk/guides/story">Next</a>', $html);
    }

    public function test_leaves_external_and_reserved_links_alone(): void
    {
        $paths = Mockery::mock(PublicContentPathResolver::class);
        $paths->shouldReceive('canonicalPathForPage')->never();

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findPublishedBySlugs')->never();

        $input = '<a href="https://example.com/x">ext</a><a href="/brand/login">login</a><a href="mailto:a@b.c">mail</a>';
        $html = (new PublicContentLinkRewriter($paths, $pages))->rewriteHtml($input, 1, 'brand', 'uk');

        self::assertSame($input, $html);
    }

    public function test_non_regional_pass_canonicalises_flat_slug(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->slug = 'story';

        $paths = Mockery::mock(PublicContentPathResolver::class);
        $paths->shouldReceive('canonicalPathForPage')->once()->with($page)->andReturn('news/story');

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findPublishedBySlugs')
            ->once()
            ->with(1, ['story'], ['categories'])
            ->andReturn(['story' => $page]);

        $html = (new PublicContentLinkRewriter($paths, $pages))->rewriteHtml(
            '<a href="/brand/story?ref=1#top">Next</a>',
            1,
            'brand',
        );

        self::assertSame('<a href="/brand/news/story?ref=1#top">Next</a>', $html);
    }

    public function test_batches_unique_slugs_across_many_links(): void
    {
        $story = Mockery::mock(Page::class)->makePartial();
        $story->slug = 'story';
        $review = Mockery::mock(Page::class)->makePartial();
        $review->slug = 'review';

        $paths = Mockery::mock(PublicContentPathResolver::class);
        $paths->shouldReceive('canonicalPathForPage')->with($story)->andReturn('story');
        $paths->shouldReceive('canonicalPathForPage')->with($review)->andReturn('review');

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findPublishedBySlugs')
            ->once()
            ->with(1, Mockery::on(static function (array $slugs): bool {
                sort($slugs);

                return $slugs === ['review', 'story'];
            }), ['categories'])
            ->andReturn([
                'story' => $story,
                'review' => $review,
            ]);

        $html = (new PublicContentLinkRewriter($paths, $pages))->rewriteHtml(
            '<a href="/brand/story">A</a><a href="/brand/review">B</a><a href="/brand/story">C</a>',
            1,
            'brand',
        );

        self::assertSame(
            '<a href="/brand/story">A</a><a href="/brand/review">B</a><a href="/brand/story">C</a>',
            $html,
        );
    }
}
