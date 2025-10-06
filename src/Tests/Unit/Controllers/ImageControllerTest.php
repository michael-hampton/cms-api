<?php

namespace App\Tests\Unit\Controllers;

use App\Controllers\ImageController;
use App\Framework\Http\Request;
use App\Framework\Http\UploadedFile;
use App\Models\Author;
use App\Models\Image;
use App\Repositories\ImageRepository;
use App\Search\PaginatedResult;
use App\Services\ImageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class ImageControllerTest extends FunctionalTestCase
{
    private $imageService;
    private $controller;
    private $imageRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageService = Mockery::mock(ImageService::class);
        $this->imageRepository = Mockery::mock(ImageRepository::class);
        $this->controller = new ImageController($this->imageService, $this->imageRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testIndexReturnsFilteredImages()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => '',
                    'search' => null,
                    'sort_by' => 'created_at',
                    'sort_order' => 'desc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $authors = collect([
            $this->createMockImage(1, 'Tag 1'),
            $this->createMockImage(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($authors->toArray(), $authors->count(), 1, 20);

        $this->imageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSortBy() === 'created_at'
                    && $criteria->getSortOrder() === 'desc';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);;

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['name']);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testStoreUploadsImageSuccessfully()
    {
        $request = Mockery::mock(Request::class);
        $file = Mockery::mock(UploadedFile::class);

        $request->shouldReceive('file')->with('image')->andReturn($file);
        $request->shouldReceive('get')->with('alt_text')->andReturn('Alt text');
        $request->shouldReceive('get')->with('caption')->andReturn('Caption');
        $request->shouldReceive('get')->with('description')->andReturn('Description');
        $request->shouldReceive('get')->with('categories', [])->andReturn([]);
        $request->shouldReceive('get')->with('site_id')->andReturn($this->siteId);

        $image = Mockery::mock(Image::class);
        $image->shouldReceive('toArrayWithUsage')->andReturn(['id' => 1]);

        $this->imageService->shouldReceive('uploadImage')
            ->once()
            ->andReturn($image);

        $response = $this->controller->store($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testStoreReturns400WhenNoFileProvided()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('file')->with('image')->andReturn(null);

        $response = $this->controller->store($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testShowReturnsImage()
    {
        $image = new Image(['id' => 1, 'filename' => 'test.jpg']);

        $this->imageService->shouldReceive('getImage')
            ->with(1)
            ->once()
            ->andReturn($image);

        $response = $this->controller->show(1);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturns404WhenImageNotFound()
    {
        $this->imageService->shouldReceive('getImage')
            ->with(999)
            ->andReturn(null);

        $response = $this->controller->show(999);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyDeletesImageSoftly()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('hard_delete', false)->andReturn(false);

        $this->imageService->shouldReceive('deleteImage')
            ->with(1, false)
            ->once()
            ->andReturn(true);

        $response = $this->controller->destroy(1, $request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBulkDestroyProcessesMultipleImages()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('image_ids', [])->andReturn([1, 2, 3]);
        $request->shouldReceive('get')->with('hard_delete', false)->andReturn(false);

        $results = ['deleted' => 3, 'failed' => 0];

        $this->imageService->shouldReceive('bulkDeleteImages')
            ->with([1, 2, 3], false)
            ->once()
            ->andReturn($results);

        $response = $this->controller->bulkDestroy($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBulkDestroyReturns400WhenNoIdsProvided()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->with('image_ids', [])
            ->andReturn([]);
        $request->shouldReceive('get')
            ->with('hard_delete', false)
            ->andReturn(false);
        $response = $this->controller->bulkDestroy($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRecentReturnsRecentImages()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('limit', 10)->andReturn(10);

        $images = collect([]);

        $this->imageService->shouldReceive('getRecentImages')
            ->with(10)
            ->once()
            ->andReturn($images);

        $response = $this->controller->recent($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testStatisticsReturnsImageStats()
    {
        $stats = ['total' => 100, 'total_size' => 1024000];

        $this->imageService->shouldReceive('getImageStatistics')
            ->once()
            ->andReturn($stats);

        $response = $this->controller->statistics();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUnusedReturnsUnusedImages()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('older_than_days')->andReturn(30);

        $images = collect([]);

        $this->imageService->shouldReceive('getUnusedImages')
            ->with(30)
            ->once()
            ->andReturn($images);

        $response = $this->controller->unused($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCleanupRemovesUnusedImages()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('older_than_days', 30)->andReturn(30);

        $results = ['deleted' => 5, 'failed' => 0, 'freed_space' => 5120];

        $this->imageService->shouldReceive('cleanupUnusedImages')
            ->with(30)
            ->once()
            ->andReturn($results);

        $response = $this->controller->cleanup($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTrackUsageTracksImageUsage()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('image_id')->andReturn(1);
        $request->shouldReceive('get')->with('usable_type')->andReturn('Page');
        $request->shouldReceive('get')->with('usable_id')->andReturn(10);
        $request->shouldReceive('get')->with('context')->andReturn('featured');

        $this->imageService->shouldReceive('trackImageUsage')
            ->with(1, 'Page', 10, 'featured')
            ->once();

        $response = $this->controller->trackUsage($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTrackUsageReturns400WhenParametersMissing()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->withArgs(fn($key) => in_array($key, ['image_id', 'usable_type', 'usable_id', 'context']))
            ->andReturnUsing(function ($key) {
                $defaults = [
                    'image_id' => null,
                    'usable_type' => 'Page',
                    'usable_id' => 10,
                    'context' => null,
                ];
                return $defaults[$key] ?? null;
            });

        $response = $this->controller->trackUsage($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    private function createMockImage($id, $name)
    {
        $tag = Mockery::mock(Image::class);
        $tag->shouldReceive('toArray')->andReturn([
            'id' => $id,
            'name' => $name
        ]);
        return $tag;
    }
}