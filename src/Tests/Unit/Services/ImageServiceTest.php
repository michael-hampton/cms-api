<?php

namespace App\Tests\Unit\Services;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\UploadedFile;
use App\Models\Image;
use App\Repositories\ImageRepository;
use App\Services\ImageService;
use App\Services\ImageUploadService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ImageServiceTest extends TestCase
{
    private $imageRepository;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageRepository = Mockery::mock(ImageRepository::class);
        $this->imageUploadService = Mockery::mock(ImageUploadService::class);
        $this->service = new ImageService($this->imageRepository, $this->imageUploadService);;

        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing'); // optional, for functions using getenv()
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testUploadImageUsesImageUploadService()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(1024);
        $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
        $file->shouldReceive('getClientOriginalName')->andReturn('test.jpg');

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->with($file, Mockery::type('string'))
            ->andReturn('images/2025-01-04/test.jpg');

        $image = Mockery::mock(Image::class)->makePartial();
        $image->id = 1;
        $image->mime_type = 'image/jpeg';
        $image->file_path = 'images/2025-01-04/test.jpg'; // ADD THIS LINE

        // Mock ensureDirectoryExists calls for thumbnails
//        $this->imageUploadService->shouldReceive('ensureDirectoryExists')
//            ->times(3); // small, medium, large

        $this->imageRepository->shouldReceive('create')
            ->once()
            ->andReturn($image);

        $result = $this->service->uploadImage($file);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testUploadImageSkipsThumbnailsForNonImageMimeTypes()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(1024);
        $file->shouldReceive('getMimeType')->andReturn('image/svg+xml'); // SVG shouldn't generate thumbnails
        $file->shouldReceive('getClientOriginalName')->andReturn('test.svg');

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->with($file, Mockery::type('string'))
            ->andReturn('images/2025-01-04/test.svg');

        $image = Mockery::mock(Image::class)->makePartial();
        $image->id = 1;
        $image->mime_type = 'image/svg+xml';
        $image->file_path = 'images/2025-01-04/test.svg';

        // Should NOT call ensureDirectoryExists for SVG
        $this->imageUploadService->shouldNotReceive('ensureDirectoryExists');

        $this->imageRepository->shouldReceive('create')
            ->once()
            ->andReturn($image);

        $result = $this->service->uploadImage($file);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testDeleteImageUsesImageUploadService()
    {
        $image = Mockery::mock(Image::class)->makePartial();
        $image->file_path = 'images/test.jpg';
        $image->shouldReceive('isUsed')->andReturn(false);
        $image->shouldReceive('delete')->andReturn(true);

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($image);

        $this->imageUploadService->shouldReceive('delete')
            ->once()
            ->with('images/test.jpg');

        $this->imageUploadService->shouldReceive('delete')
            ->times(3); // For thumbnails

        $result = $this->service->deleteImage(1, true);
        $this->assertTrue($result);
    }

    public function testUploadImageValidatesFile()
    {
        $this->expectException(ValidationException::class);

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(false);

        $this->service->uploadImage($file);
    }

    public function testUploadImageRejectsOversizedFiles()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds maximum allowed size');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(11 * 1024 * 1024); // 11MB
        $file->shouldReceive('getMimeType')->andReturn('image/jpeg');

        $this->service->uploadImage($file);
    }

    public function testUploadImageRejectsInvalidMimeType()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File type not allowed');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(1024);
        $file->shouldReceive('getMimeType')->andReturn('application/pdf');

        $this->service->uploadImage($file);
    }

    public function testGetImagesAppliesFiltersCorrectly()
    {
        $filters = [
            'query' => 'test',
            'mime_type' => 'image/jpeg',
            'page' => 1,
            'per_page' => 20
        ];

        $this->imageRepository->shouldReceive('searchImages')
            ->with(
                'test',
                'image/jpeg',
                null,
                1,
                20,
                'created_at',
                'desc'
            )
            ->once()
            ->andReturn([
                'data' => collect([]),
                'total' => 0,
                'per_page' => 20,
                'current_page' => 1,
                'total_pages' => 0,
                'has_more' => false
            ]);

        $result = $this->service->getImages($filters);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testDeleteImageThrowsExceptionWhenImageInUse()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('currently in use');

        $image = Mockery::mock(Image::class);
        $image->shouldReceive('isUsed')->andReturn(true);

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($image);

        $this->service->deleteImage(1, true);
    }

    public function testBulkDeleteImagesReturnsResults()
    {
        $image1 = Mockery::mock(Image::class);
        $image1->shouldReceive('isUsed')->andReturn(false);
        $image1->shouldReceive('softDelete')->andReturn(true);

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($image1);

        $this->imageRepository->shouldReceive('find')
            ->with(2)
            ->andReturn(null);

        $results = $this->service->bulkDeleteImages([1, 2], false);

        $this->assertEquals(1, $results['deleted']);
        $this->assertEquals(1, $results['failed']);
    }

    public function testGetImageStatisticsCallsRepository()
    {
        $stats = [
            'total' => 100,
            'total_size' => 1024000
        ];

        $this->imageRepository->shouldReceive('getImageStatistics')
            ->once()
            ->andReturn($stats);

        $result = $this->service->getImageStatistics();

        $this->assertEquals($stats, $result);
    }

    public function testCleanupUnusedImagesDeletesOldImages()
    {
        $image = Mockery::mock(Image::class)->makePartial();
        $image->id = 1;
        $image->file_size = 1024;
        $image->file_path = 'test';

        $this->imageRepository->shouldReceive('getUnusedImages')
            ->with(30)
            ->andReturn(collect([$image]));

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($image);

        $image->shouldReceive('isUsed')->andReturn(false);
        $image->shouldReceive('delete')->andReturn(true);

        $this->imageUploadService->shouldReceive('delete')->with('test')->andReturn(true);
        $this->imageUploadService->shouldReceive('delete')->with('./thumbs/small/test')->andReturn(true);
        $this->imageUploadService->shouldReceive('delete')->with('./thumbs/medium/test')->andReturn(true);
        $this->imageUploadService->shouldReceive('delete')->with('./thumbs/large/test')->andReturn(true);

        $results = $this->service->cleanupUnusedImages(30);

        $this->assertEquals(1, $results['deleted']);
        $this->assertEquals(1024, $results['freed_space']);
    }
}