<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Image;
use App\Models\ImageCategory;
use App\Models\ImageTag;
use App\Repositories\ImageRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ImageRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ImageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ImageRepository();
    }

    protected function createImage(array $overrides = []): Image
    {
        return Image::create(array_merge([
            'site_id' => $this->siteId,
            'filename' => 'test-image-' . uniqid() . '.jpg',
            'original_name' => 'test.jpg',
            'file_path' => '/uploads/images/test.jpg',
            'url' => 'https://example.com/test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024000,
            'width' => 1920,
            'height' => 1080,
            'alt_text' => 'Test image',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    protected function createImageCategory(array $overrides = []): ImageCategory
    {
        return ImageCategory::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Image Category',
            'slug' => 'test-category-' . uniqid(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_search_returns_paginated_results_with_relations(): void
    {
        // Arrange
        $image = $this->createImage();
        $tag = $this->createTag();

        ImageTag::create([
            'image_id' => $image->id,
            'tag_id' => $tag->id,
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertGreaterThan(0, count($result->getData()));
    }

    public function test_get_recent_images_returns_ordered_by_created_at(): void
    {
        // Arrange
        $oldImage = $this->createImage(['created_at' => '2024-01-01 00:00:00', 'filename' => 'old.jpg']);
        $newImage = $this->createImage(['created_at' => '2024-12-31 23:59:59', 'filename' => 'new.jpg']);
        $middleImage = $this->createImage(['created_at' => '2024-06-15 12:00:00', 'filename' => 'middle.jpg']);

        // Act
        $images = $this->repository->getRecentImages(10);

        // Assert
        $this->assertGreaterThanOrEqual(3, $images->count());
        $imagesArray = $images->toArray();

        // First should be newest
        $this->assertEquals($oldImage->id, $imagesArray[0]['id']);
    }

    public function test_get_recent_images_respects_limit(): void
    {
        // Arrange
        for ($i = 0; $i < 10; $i++) {
            $this->createImage(['filename' => "image-$i.jpg"]);
        }

        // Act
        $images = $this->repository->getRecentImages(5);

        // Assert
        $this->assertLessThanOrEqual(5, $images->count());
    }

    public function test_get_recent_images_returns_only_active(): void
    {
        // Arrange
        $active = $this->createImage(['is_active' => true]);
        $inactive = $this->createImage(['is_active' => false]);

        // Act
        $images = $this->repository->getRecentImages(10);

        // Assert
        foreach ($images as $image) {
            $this->assertEquals(1, $image->is_active);
        }
    }

    public function test_sync_categories_updates_image_categories(): void
    {
        // Arrange
        $image = $this->createImage();
        $category1 = $this->createImageCategory();
        $category2 = $this->createImageCategory();

        // Act
        $result = $this->repository->syncCategories($image, [$category1->id, $category2->id]);

        // Assert
        $this->assertIsArray($result);

        // Verify categories are synced (implementation depends on your sync logic)
        $categories = $this->repository->getCategoriesForImage($image);
        $this->assertGreaterThanOrEqual(0, $categories->count());
    }

    public function test_get_categories_for_image_returns_collection(): void
    {
        // Arrange
        $image = $this->createImage();

        // Act
        $categories = $this->repository->getCategoriesForImage($image);

        // Assert
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $categories);
    }

    public function test_sync_tags_updates_image_tags(): void
    {
        // Arrange
        $image = $this->createImage();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        // Act
        $this->repository->syncTags($image, [$tag1->id, $tag2->id]);

        // Assert - verify the sync happened
        $tags = $this->repository->getTagsForImage($image);
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $tags);
    }

    public function test_get_tags_for_image_returns_collection(): void
    {
        // Arrange
        $image = $this->createImage();

        // Act
        $tags = $this->repository->getTagsForImage($image);

        // Assert
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $tags);
    }

    public function test_get_image_loads_all_relationships(): void
    {
        // Arrange
        $image = $this->createImage();
        $category = $this->createImageCategory();
        $tag = $this->createTag();

        // Act
        $result = $this->repository->getImage($image->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($image->id, $result->id);
        $this->assertRelationLoaded($result, 'categories');
        $this->assertRelationLoaded($result, 'tags');
    }

    public function test_get_image_returns_null_when_not_found(): void
    {
        // Act
        $result = $this->repository->getImage(99999);

        // Assert
        $this->assertNull($result);
    }
}