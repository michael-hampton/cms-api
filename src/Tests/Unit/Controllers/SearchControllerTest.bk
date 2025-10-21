<?php

namespace App\Tests\Unit\Controllers;

use App\Controllers\SearchController;
use App\Framework\Http\Request;
use App\Models\Page;
use App\Repositories\PageRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class SearchControllerTest extends TestCase
{
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->controller = new SearchController($this->pageRepository);;

    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testPagesSearchesAndReturnsResults()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('q', '')->andReturn('test');
        $request->shouldReceive('get')->with('limit', 20)->andReturn(20);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->title = 'Test Page 1';
        $page->slug = 'test-page-1';
        $page->status = 'published';
        $page->page_type = 'article';

        $expectedCollection = collect([$page]);

        // Updated to use quickSearch
        $this->pageRepository->shouldReceive('quickSearch')
            ->with('test', [
                'limit' => 20,
                'status' => 'published'
            ])
            ->once()
            ->andReturn($expectedCollection);

        $response = $this->controller->pages($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
    }

    public function testPagesLimitsResultsTo50Maximum()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('q', '')->andReturn('test');
        $request->shouldReceive('get')->with('limit', 20)->andReturn(100);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->title = 'Test Page 1';
        $page->slug = 'test-page-1';
        $page->status = 'published';
        $page->page_type = 'article';

        $expectedCollection = collect([$page]);

        // Should limit to 50 maximum
        $this->pageRepository->shouldReceive('quickSearch')
            ->with('test', [
                'limit' => 50,
                'status' => 'published'
            ])
            ->once()
            ->andReturn($expectedCollection);

        $response = $this->controller->pages($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPagesReturnsEmptyArrayWhenNoResults()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('q', '')->andReturn('nonexistent');
        $request->shouldReceive('get')->with('limit', 20)->andReturn(20);

        $this->pageRepository->shouldReceive('quickSearch')
            ->with('nonexistent', [
                'limit' => 20,
                'status' => 'published'
            ])
            ->once()
            ->andReturn(collect([]));

        $response = $this->controller->pages($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['data']);
    }
}