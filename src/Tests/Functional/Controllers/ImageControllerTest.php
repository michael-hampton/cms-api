<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Image;
use App\Models\ImageCategory;

class ImageControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');
    }

    public function testIndexReturnsImagesList()
    {
        $image = Image::create([
            'url' => 'test',
            'file_size' => 6,
            'filename' => 'test.jpg',
            'file_path' => '/uploads/test.jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'site_id' => $this->siteId
        ]);;
        $response = $this->getForSite('/api/images');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testStoreUploadsImage()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $files = [
            'image' => $this->createUploadedFile('avatar.jpg', 'image/jpeg')
        ];
        $response = $this->postForSite('/api/images', ['alt_text' => 'Test image', 'caption' => 'Caption'], $files);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);



        $this->assertEquals('Test image', $data['data']['image']['alt_text']);
    }

    public function testStoreWithCategories()
    {
        $category = ImageCategory::create(['name' => 'Photos', 'slug' => 'photos']);

        $files = [
            'image' => $this->createUploadedFile('avatar.jpg', 'image/jpeg')
        ];

        $response = $this->postForSite('/api/images', ['alt_text' => 'Test', 'categories' => [$category->id]], $files);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testStoreValidatesFileRequired()
    {
        $response = $this->postForSite('/api/images', ['alt_text' => 'Test']);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testShowReturnsImage()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->getForSite("/api/images/{$image->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('test.jpg', $data['data']['image']['filename']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->get('/api/images/999');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesMetadata()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->putForSite("/api/images/{$image->id}", ['alt_text' => 'Updated alt', 'caption' => 'New caption']);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated alt', $data['data']['image']['alt_text']);
    }

    public function testDestroyMovesToTrash()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->deleteForSite("/api/images/{$image->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('trash', $response->getContent());
    }

    public function testDestroyHardDelete()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->deleteForSite("/api/images/{$image->id}?hard_delete=true");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('permanently', $response->getContent());
    }

//    public function testBulkDestroy()
//    {
//        $img1 = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test1.jpg', 'file_path' => '/uploads/test1.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
//        $img2 = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test2.jpg', 'file_path' => '/uploads/test2.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
//        $response = $this->post('/api/images/bulk-delete', ['image_ids' => [$img1->id, $img2->id]]);
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertArrayHasKey('results', $data);
//    }

    public function testRecentReturnsLatestImages()
    {
        for ($i = 1; $i <= 15; $i++) {
            $image = Image::create(['url' => 'test'.$i, 'file_size' => 6, 'filename' => 'test.jpg'.$i, 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        }
        $response = $this->getForSite('/api/image-recent?limit=5');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(5, $data['data']['images']);
    }

//    public function testStatistics()
//    {
//        $response = $this->get('/api/images/statistics');
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertArrayHasKey('statistics', $data);
//    }

//    public function testUnusedReturnsUnusedImages()
//    {
//        Image::create(['filename' => 'unused.jpg', 'path' => '/uploads/unused.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg']);
//        $response = $this->get('/api/images/unused');
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getBody(), true);
//        $this->assertArrayHasKey('images', $data);
//        $this->assertArrayHasKey('count', $data);
//    }

//    public function testCleanupRemovesOldUnusedImages()
//    {
//        $response = $this->post('/api/images/cleanup', ['older_than_days' => 30]);
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertArrayHasKey('results', $data);
//    }

    public function testTrackUsage()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->postForSite('/api/image-track-usage', ['image_id' => $image->id, 'usable_type' => 'Page', 'usable_id' => 1, 'context' => 'featured']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRemoveUsage()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->postForSite('/api/image-remove-usage', ['image_id' => $image->id, 'usable_type' => 'Page', 'usable_id' => 1, 'context' => 'featured']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCategoriesReturnsAllCategories()
    {
        ImageCategory::create(['name' => 'Photos', 'slug' => 'photos', 'site_id' => $this->siteId]);;
        ImageCategory::create(['name' => 'Graphics', 'slug' => 'graphics', 'site_id' => $this->siteId]);;;
        $response = $this->getForSite('/api/image-categories');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']['categories']);
    }

    public function testCreateCategory()
    {
        $response = $this->postForSite('/api/image-categories', ['name' => 'New Category', 'description' => 'Test']);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('New Category', $data['data']['category']['name']);
    }

    public function testDuplicateCreatesNewImage()
    {
        $this->createTempUploadFile('test.jpg');

        $original = Image::create([
            'url' => 'test',
            'file_size' => 1024,
            'filename' => 'test.jpg',
            'file_path' => 'uploads_test/test.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'alt_text' => 'Original'
        ]);

        $response = $this->postForSite("/api/images/{$original->id}/duplicate", [
            'alt_text' => 'Duplicated image'
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Duplicated image', $data['data']['image']['alt_text']);
        $this->assertStringContainsString('duplicated successfully', $data['data']['message']);
    }

    public function testDuplicateWithoutMetadataGeneratesDefaults()
    {
        $this->createTempUploadFile('test.jpg');

        $original = Image::create([
            'url' => 'test',
            'file_size' => 1024,
            'filename' => 'test.jpg',
            'file_path' => 'uploads_test/test.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'alt_text' => 'Original alt',
            'caption' => 'Original caption'
        ]);

        $response = $this->postForSite("/api/images/{$original->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('copy', strtolower($data['data']['image']['alt_text']));
    }

    public function testDuplicateReturns500WhenImageNotFound()
    {
        $response = $this->postForSite('/api/images/999/duplicate');
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testStoreWithName()
    {
        $files = [
            'image' => $this->createUploadedFile('avatar.jpg', 'image/jpeg')
        ];

        $response = $this->postForSite('/api/images', [
            'name' => 'My Custom Name',
            'alt_text' => 'Test image'
        ], $files);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('My Custom Name', $data['data']['image']['name']);
    }

    public function testStoreWithTags()
    {
        $tag1 = \App\Models\Tag::create(['name' => 'Photo', 'slug' => 'photo', 'site_id' => $this->siteId]);
        $tag2 = \App\Models\Tag::create(['name' => 'Portrait', 'slug' => 'portrait', 'site_id' => $this->siteId]);

        $files = [
            'image' => $this->createUploadedFile('avatar.jpg', 'image/jpeg')
        ];

        $response = $this->postForSite('/api/images', [
            'alt_text' => 'Test',
            'tags' => [$tag1->id, $tag2->id]
        ], $files);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $imageId = $data['data']['image']['id'];

        // Verify tags were assigned
        $image = Image::find($imageId);

        $this->assertCount(2, $image->tags());
    }

    public function testUpdateWithNameAndTags()
    {
        $image = Image::create([
            'url' => 'test',
            'file_size' => 6,
            'filename' => 'test.jpg',
            'file_path' => '/uploads/test.jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'name' => 'Old Name',
            'site_id' => $this->siteId
        ]);

        $tag = \App\Models\Tag::create(['name' => 'Updated', 'slug' => 'updated', 'site_id' => $this->siteId]);

        $response = $this->putForSite("/api/images/{$image->id}", [
            'name' => 'New Name',
            'tags' => [$tag->id]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('New Name', $data['data']['image']['name']);

        $updatedImage = Image::find($image->id);
        $this->assertCount(1, $updatedImage->tags());
    }

    public function testDuplicateCopiesNameAndTags()
    {
        $this->createTempUploadFile('test.jpg');

        $tag = \App\Models\Tag::create(['name' => 'Original', 'slug' => 'original', 'site_id' => $this->siteId]);

        $original = Image::create([
            'url' => 'test',
            'file_size' => 1024,
            'filename' => 'test.jpg',
            'file_path' => 'uploads_test/test.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'name' => 'Original Name',
            'site_id' => $this->siteId
        ]);

        $original->syncTags([$tag->id]);

        $response = $this->postForSite("/api/images/{$original->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('copy', strtolower($data['data']['image']['name']));

        $duplicateId = $data['data']['image']['id'];
        $duplicate = Image::find($duplicateId);
        $this->assertCount(1, $duplicate->tags());
    }
}