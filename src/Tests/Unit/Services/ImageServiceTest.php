<?php

namespace App\Tests\Unit\Services;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Models\Image;
use App\Models\ImageCategory;
use App\Models\ImageTag;
use App\Models\Tag;
use App\Repositories\ImageRepository;
use App\Services\ImageService;
use App\Services\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Exception;
use Mockery;
use PHPUnit\Framework\TestCase;

class ImageServiceTest extends FunctionalTestCase
{
    private $imageRepository;
    private $service;
    private $imageUploadService;

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

        $this->imageRepository->shouldReceive('syncTags')
            ->once()
            ->with($image, []);

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

        $this->imageRepository->shouldReceive('syncTags')
            ->once()
            ->with($image, []);

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

    public function testGetImagesUsesSearchCriteria()
    {
        $filters = [
            'query' => 'test',
            'mime_type' => 'image/jpeg',
            'category_id' => 5,
            'page' => 2,
            'per_page' => 15,
            'sort_by' => 'file_size',
            'sort_order' => 'asc'
        ];

        $paginatedResult = new \App\Search\PaginatedResult(
            data: [],
            total: 0,
            page: 2,
            perPage: 15
        );

        $this->imageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) use ($filters) {
                return $criteria instanceof \App\Search\SearchCriteria
                    && $criteria->getSearchQuery() === $filters['query']
                    && $criteria->getFilters()['mime_type'] === $filters['mime_type']
                    && $criteria->getFilters()['category'] === $filters['category_id']
                    && $criteria->getSortBy() === $filters['sort_by']
                    && $criteria->getSortOrder() === $filters['sort_order']
                    && $criteria->getPage() === $filters['page']
                    && $criteria->getPerPage() === $filters['per_page'];
            }))
            ->andReturn($paginatedResult);

        $result = $this->service->getImages($filters);

        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
    }

    public function testGetImagesLimitsPerPageTo100()
    {
        $filters = ['per_page' => 200]; // Try to request too many

        $paginatedResult = new \App\Search\PaginatedResult(
            data: [],
            total: 0,
            page: 1,
            perPage: 100
        );

        $this->imageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getPerPage() === 100; // Should be clamped
            }))
            ->andReturn($paginatedResult);

        $result = $this->service->getImages($filters);

        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
    }

    public function testGetImagesMinimumPerPageIs1()
    {
        $filters = ['per_page' => -5]; // Try to request negative

        $paginatedResult = new \App\Search\PaginatedResult(
            data: [],
            total: 0,
            page: 1,
            perPage: 1
        );

        $this->imageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getPerPage() === 1; // Should be at least 1
            }))
            ->andReturn($paginatedResult);

        $result = $this->service->getImages($filters);

        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
    }

    public function testGetImagesUsesDefaultsWhenNoFilters()
    {
        $paginatedResult = new \App\Search\PaginatedResult(
            data: [],
            total: 0,
            page: 1,
            perPage: 20
        );

        $this->imageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSearchQuery() === ''
                    && empty(array_filter($criteria->getFilters()))
                    && $criteria->getSortBy() === 'created_at'
                    && $criteria->getSortOrder() === 'desc'
                    && $criteria->getPage() === 1
                    && $criteria->getPerPage() === 20;
            }))
            ->andReturn($paginatedResult);

        $result = $this->service->getImages([]);

        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
    }

    public function testGetImagesFiltersCategoryCorrectly()
    {
        $filters = ['category_id' => 3];

        $paginatedResult = new \App\Search\PaginatedResult(
            data: [],
            total: 0,
            page: 1,
            perPage: 20
        );

        $this->imageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getFilters()['category'] === 3;
            }))
            ->andReturn($paginatedResult);

        $result = $this->service->getImages($filters);

        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
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

    public function testDuplicateImageCreatesNewRecord()
    {
        $originalImage = Mockery::mock(Image::class)->makePartial();
        $originalImage->id = 1;
        $originalImage->file_path = 'images/2025-01-04/original.jpg';
        $originalImage->original_name = 'original.jpg';
        $originalImage->mime_type = 'image/jpeg';
        $originalImage->file_size = 1024;
        $originalImage->width = 800;
        $originalImage->height = 600;
        $originalImage->alt_text = 'Original alt';
        $originalImage->caption = 'Original caption';
        $originalImage->description = 'Original description';
        $originalImage->site_id = $this->siteId;

        $originalImage->shouldReceive('categories->get')
            ->andReturn(collect([]));

        $newImage = Mockery::mock(Image::class)->makePartial();
        $newImage->id = 2;
        $newImage->mime_type = 'image/jpeg';
        $newImage->file_path = 'images/2025-01-04/original-copy-abc123.jpg';

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($originalImage);

        $this->imageUploadService->shouldReceive('duplicate')
            ->once()
            ->with('images/2025-01-04/original.jpg')
            ->andReturn('images/2025-01-04/original-copy-abc123.jpg');

        $this->imageRepository->shouldReceive('create')
            ->once()
            ->andReturn($newImage);

        $this->imageRepository->shouldReceive('getCategoriesForImage')
            ->with($originalImage)
            ->andReturn(collect([]));
        $this->imageRepository->shouldReceive('getTagsForImage')
            ->with($originalImage)
            ->andReturn(collect([]));

        $result = $this->service->duplicateImage(1);

        $this->assertInstanceOf(Image::class, $result);
        $this->assertEquals(2, $result->id);
    }

    public function testDuplicateImageCopiesMetadata()
    {
        $originalImage = Mockery::mock(Image::class)->makePartial();
        $originalImage->id = 1;
        $originalImage->file_path = 'images/test.jpg';
        $originalImage->original_name = 'test.jpg';
        $originalImage->mime_type = 'image/jpeg';
        $originalImage->file_size = 1024;
        $originalImage->width = 800;
        $originalImage->height = 600;
        $originalImage->alt_text = 'Original alt';
        $originalImage->caption = 'Original caption';
        $originalImage->description = 'Original description';

        $originalImage->shouldReceive('categories->get')
            ->andReturn(collect([]));

        $newImage = Mockery::mock(Image::class)->makePartial();
        $newImage->id = 2;
        $newImage->mime_type = 'image/jpeg';
        $newImage->file_path = 'images/test-copy.jpg';

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($originalImage);

        $this->imageUploadService->shouldReceive('duplicate')
            ->andReturn('images/test-copy.jpg');

        $this->imageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['alt_text'] === 'Original alt (copy)'
                    && $data['caption'] === 'Original caption (copy)'
                    && $data['description'] === 'Original description'
                    && $data['original_name'] === 'test-copy.jpg';
            }))
            ->andReturn($newImage);

        $this->imageRepository->shouldReceive('getCategoriesForImage')
            ->with($originalImage)
            ->andReturn(collect([]));

        $this->imageRepository->shouldReceive('getTagsForImage')
            ->with($originalImage)
            ->andReturn(collect([]));


        $result = $this->service->duplicateImage(1);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testDuplicateImageWithCustomMetadata()
    {
        $originalImage = Mockery::mock(Image::class)->makePartial();
        $originalImage->id = 1;
        $originalImage->file_path = 'images/test.jpg';
        $originalImage->original_name = 'test.jpg';
        $originalImage->mime_type = 'image/jpeg';
        $originalImage->file_size = 1024;
        $originalImage->width = 800;
        $originalImage->height = 600;
        $originalImage->alt_text = 'Original alt';

        $originalImage->shouldReceive('categories->get')
            ->andReturn(collect([]));

        $newImage = Mockery::mock(Image::class)->makePartial();
        $newImage->id = 2;
        $newImage->mime_type = 'image/jpeg';
        $newImage->file_path = 'images/test-copy.jpg';

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($originalImage);

        $this->imageRepository->shouldReceive('getCategoriesForImage')
            ->with($originalImage)
            ->andReturn(collect([]));

        $this->imageRepository->shouldReceive('getTagsForImage')
            ->with($originalImage)
            ->andReturn(collect([]));

        $this->imageUploadService->shouldReceive('duplicate')
            ->andReturn('images/test-copy.jpg');

        $this->imageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['alt_text'] === 'Custom alt text';
            }))
            ->andReturn($newImage);

        $result = $this->service->duplicateImage(1, [
            'alt_text' => 'Custom alt text'
        ]);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testDuplicateImageCopiesCategories()
    {
        $category1 = Mockery::mock(ImageCategory::class)->makePartial();
        $category1->id = 1;
        $category2 = Mockery::mock(ImageCategory::class)->makePartial();
        $category2->id = 2;

        $originalImage = Mockery::mock(Image::class)->makePartial();
        $originalImage->id = 1;
        $originalImage->file_path = 'images/test.jpg';
        $originalImage->original_name = 'test.jpg';
        $originalImage->mime_type = 'image/jpeg';
        $originalImage->file_size = 1024;

        $categoriesCollection = Mockery::mock(Collection::class, [[$category1, $category2]]);

        $this->imageRepository->shouldReceive('getCategoriesForImage')
            ->with($originalImage)
            ->andReturn($categoriesCollection);

        $this->imageRepository->shouldReceive('getTagsForImage')
            ->with($originalImage)
            ->andReturn(collect([]));

        $categoriesCollection->shouldReceive('count')->andReturn(2)->once();
        $categoriesCollection->shouldReceive('pluck')
            ->with('id')
            ->andReturn(collect([1, 2]));
        $categoriesCollection->shouldReceive('toArray')
            ->andReturn([['id' => 1], ['id' => 2]]);

        $originalImage->shouldReceive('categories->get')
            ->andReturn($categoriesCollection);

        $newImage = Mockery::mock(Image::class)->makePartial();
        $newImage->id = 2;
        $newImage->mime_type = 'image/jpeg';
        $newImage->file_path = 'images/test-copy.jpg';

        $this->imageRepository->shouldReceive('syncCategories')
            ->with($newImage, [1,2])
            ->once();

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($originalImage);

        $this->imageUploadService->shouldReceive('duplicate')
            ->andReturn('images/test-copy.jpg');

        $this->imageRepository->shouldReceive('create')
            ->andReturn($newImage);

        $result = $this->service->duplicateImage(1);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testDuplicateImageThrowsExceptionWhenNotFound()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Image not found');

        $this->imageRepository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $this->service->duplicateImage(999);
    }

    public function testDuplicateImageSkipsThumbnailsForSvg()
    {
        $originalImage = Mockery::mock(Image::class)->makePartial();
        $originalImage->id = 1;
        $originalImage->file_path = 'images/test.svg';
        $originalImage->original_name = 'test.svg';
        $originalImage->mime_type = 'image/svg+xml';
        $originalImage->file_size = 512;

        $originalImage->shouldReceive('categories->get')
            ->andReturn(collect([]));

        $newImage = Mockery::mock(Image::class)->makePartial();
        $newImage->id = 2;
        $newImage->mime_type = 'image/svg+xml';
        $newImage->file_path = 'images/test-copy.svg';

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($originalImage);

        $this->imageRepository->shouldReceive('getCategoriesForImage')
            ->with($originalImage)
            ->andReturn(collect([]));

        $this->imageRepository->shouldReceive('getTagsForImage')
            ->with($originalImage)
            ->andReturn(collect([]));

        $this->imageUploadService->shouldReceive('duplicate')
            ->once()
            ->andReturn('images/test-copy.svg');

        // Should NOT call ensureDirectoryExists for SVG thumbnails
        $this->imageUploadService->shouldNotReceive('ensureDirectoryExists');

        $this->imageRepository->shouldReceive('create')
            ->once()
            ->andReturn($newImage);

        $result = $this->service->duplicateImage(1);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testUploadImageWithName()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(1024);
        $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
        $file->shouldReceive('getClientOriginalName')->andReturn('test.jpg');

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->andReturn('images/2025-01-04/test.jpg');

        $image = Mockery::mock(Image::class)->makePartial();
        $image->id = 1;
        $image->mime_type = 'image/jpeg';
        $image->file_path = 'images/2025-01-04/test.jpg';

        $this->imageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['name'] === 'Custom Name';
            }))
            ->andReturn($image);

        $this->imageRepository->shouldReceive('syncTags')
            ->once()
            ->with($image, [1, 2]);

        $result = $this->service->uploadImage($file, [
            'name' => 'Custom Name',
            'tags' => [1, 2]
        ]);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testUpdateMetadataWithNameAndTags()
    {
        $image = Mockery::mock(Image::class)->makePartial();
        $image->id = 1;

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($image);

        $updateData = [
            'name' => 'Updated Name',
            'tags' => [3, 4]
        ];

        $this->imageRepository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function($data) {
                return $data['name'] === 'Updated Name';
            }));

        $this->imageRepository->shouldReceive('syncTags')
            ->once()
            ->with($image, [3, 4]);

        $image->expects('fresh')->once()->andReturn($image);

        $result = $this->service->updateImageMetadata(1, $updateData);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testDuplicateImageCopiesNameAndTags()
    {
        $tag1 = new ImageTag(['tag_id' => 1, 'name' => 'Tag 1']);
        $tag2 = new ImageTag(['tag_id' => 2, 'name' => 'Tag 2']);

        $originalImage = Mockery::mock(Image::class)->makePartial();
        $originalImage->id = 1;
        $originalImage->file_path = 'images/test.jpg';
        $originalImage->original_name = 'test.jpg';
        $originalImage->name = 'Original Name';
        $originalImage->mime_type = 'image/jpeg';
        $originalImage->file_size = 1024;

        $tagsCollection = collect([$tag1, $tag2]);

        $this->imageRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($originalImage);

        $this->imageRepository->shouldReceive('getTagsForImage')
            ->with($originalImage)
            ->andReturn($tagsCollection);

        $this->imageRepository->shouldReceive('getCategoriesForImage')
            ->with($originalImage)
            ->andReturn(collect([]));

        $this->imageUploadService->shouldReceive('duplicate')
            ->andReturn('images/test-copy.jpg');

        $newImage = Mockery::mock(Image::class)->makePartial();
        $newImage->id = 2;
        $newImage->mime_type = 'image/jpeg';
        $newImage->file_path = 'images/test-copy.jpg';

        $this->imageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['name'] === 'Original Name (copy)';
            }))
            ->andReturn($newImage);

        $this->imageRepository->shouldReceive('syncTags')
            ->once()
            ->with($newImage, [1, 2]);

        $result = $this->service->duplicateImage(1);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testUploadImageWithImageRights()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(1024);
        $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
        $file->shouldReceive('getClientOriginalName')->andReturn('test.jpg');

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->andReturn('images/2025-01-04/test.jpg');

        $image = Mockery::mock(Image::class)->makePartial();
        $image->id = 1;
        $image->mime_type = 'image/jpeg';
        $image->file_path = 'images/2025-01-04/test.jpg';

        $this->imageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['image_rights'] === 'royalty_free';
            }))
            ->andReturn($image);

        $this->imageRepository->shouldReceive('syncTags')
            ->once();

        $result = $this->service->uploadImage($file, [
            'image_rights' => 'royalty_free',
            'tags' => []
        ]);

        $this->assertInstanceOf(Image::class, $result);
    }

    public function testUploadImageWithInvalidImageRights()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid image rights');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(1024);
        $file->shouldReceive('getMimeType')->andReturn('image/jpeg');

        $this->service->uploadImage($file, [
            'image_rights' => 'invalid_rights'
        ]);
    }
}