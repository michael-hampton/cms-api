<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkRemoveTagsFromPages;
use App\Models\Page;
use App\Models\Tag;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\PageTagRepository;
use App\Repositories\Cms\TagRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkRemoveTagsFromPagesActionTest extends FunctionalTestCase
{
    private $pageRepository;
    private $pageTagRepository;
    private $tagRepository;
    private $service;

    public function testBulkRemoveTagsSuccessfully()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();

        $tag20 = Mockery::mock(Tag::class)->makePartial();
        $tag20->id = 20;
        $tag20->name = 'Tag 20';

        // Mock tag lookup first
        $this->tagRepository->shouldReceive('find')->with(20)->andReturn($tag20);

        // Then mock page lookup
        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);

        $existingTag10 = Mockery::mock(Tag::class)->makePartial();
        $existingTag10->id = 10;
        $existingTag10->name = 'Tag 10';

        $existingTag20 = Mockery::mock(Tag::class)->makePartial();
        $existingTag20->id = 20;
        $existingTag20->name = 'Tag 20';

        $existingTag30 = Mockery::mock(Tag::class)->makePartial();
        $existingTag30->id = 30;
        $existingTag30->name = 'Tag 30';

        $this->pageTagRepository->shouldReceive('getPageTags')->with(1, 1)
            ->andReturn([$existingTag10, $existingTag20, $existingTag30]);

        $this->pageTagRepository->shouldReceive('syncTags')
            ->once()
            ->with(1, Mockery::on(function ($tagNames) {
                // Check that Tag 20 is removed
                sort($tagNames);
                return $tagNames === ['Tag 10', 'Tag 30'];
            }), 1);

        $results = $this->service->handle([1], [20], 1);

        $this->assertTrue($results[1]['success']);
    }

    public function testBulkRemoveTagsHandlesPageNotFound()
    {
        $tag20 = Mockery::mock(Tag::class)->makePartial();
        $tag20->id = 20;
        $tag20->name = 'Tag 20';

        // Mock tag lookup first (happens before page lookup)
        $this->tagRepository->shouldReceive('find')->with(20)->andReturn($tag20);

        // Then mock page not found
        $this->pageRepository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([999], [20], 1);

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Page not found', $results[999]['error']);
    }

    public function testBulkRemoveTagsHandlesInvalidTags()
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

        $this->service = new BulkRemoveTagsFromPages(
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