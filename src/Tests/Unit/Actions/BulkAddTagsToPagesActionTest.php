<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkAddTagsToPages;
use App\Models\Page;
use App\Models\Tag;
use App\Repositories\PageRepository;
use App\Repositories\PageTagRepository;
use App\Repositories\TagRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkAddTagsToPagesActionTest extends FunctionalTestCase
{
    private $pageRepository;
    private $pageTagRepository;
    private $tagRepository;
    private $service;

    public function testBulkAddTagsSuccessfully()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();
        $page2 = Mockery::mock(Page::class)->makePartial();

        $tag30 = Mockery::mock(Tag::class)->makePartial();
        $tag30->id = 30;
        $tag30->name = 'Tag 30';

        $tag40 = Mockery::mock(Tag::class)->makePartial();
        $tag40->id = 40;
        $tag40->name = 'Tag 40';

        // Mock tag lookups first
        $this->tagRepository->shouldReceive('find')->with(30)->andReturn($tag30);
        $this->tagRepository->shouldReceive('find')->with(40)->andReturn($tag40);

        // Then mock page lookups
        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);
        $this->pageRepository->shouldReceive('find')->with(2)->andReturn($page2);

        $existingTag10 = (object)['id' => 10, 'name' => 'Tag 10'];
        $existingTag20 = (object)['id' => 20, 'name' => 'Tag 20'];

        $this->pageTagRepository->shouldReceive('getTagsForPage')->with(1)
            ->andReturn(collect([$existingTag10]));
        $this->pageTagRepository->shouldReceive('getTagsForPage')->with(2)
            ->andReturn(collect([$existingTag20]));

        $this->pageTagRepository->shouldReceive('syncTags')
            ->with(1, Mockery::on(function ($tagNames) {
                // Check that the array contains the expected tags
                sort($tagNames);
                return $tagNames === ['Tag 10', 'Tag 30', 'Tag 40'];
            }), 1)
            ->once();

        $this->pageTagRepository->shouldReceive('syncTags')
            ->with(2, Mockery::on(function ($tagNames) {
                // Check that the array contains the expected tags
                sort($tagNames);
                return $tagNames === ['Tag 20', 'Tag 30', 'Tag 40'];
            }), 1)
            ->once();

        $results = $this->service->handle([1, 2], [30, 40], 1);

        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
    }

    public function testBulkAddTagsHandlesPageNotFound()
    {
        $tag30 = Mockery::mock(Tag::class)->makePartial();
        $tag30->id = 30;
        $tag30->name = 'Tag 30';

        // Mock tag lookup first (happens before page lookup)
        $this->tagRepository->shouldReceive('find')->with(30)->andReturn($tag30);

        // Then mock page not found
        $this->pageRepository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([999], [30], 1);

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Page not found', $results[999]['error']);
    }

    public function testBulkAddTagsHandlesInvalidTags()
    {
        // Mock tag not found
        $this->tagRepository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([1], [999], 1);

        $this->assertFalse($results[1]['success']);
        $this->assertEquals('No valid tags provided', $results[1]['error']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->pageTagRepository = Mockery::mock(PageTagRepository::class);
        $this->tagRepository = Mockery::mock(TagRepository::class);

        $this->service = new BulkAddTagsToPages(
            $this->pageRepository,
            $this->pageTagRepository,
            $this->tagRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}