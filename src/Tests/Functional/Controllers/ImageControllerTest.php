<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Image;
use App\Models\ImageCategory;

class ImageControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testIndexReturnsImagesList()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->get('/api/images');
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
        $response = $this->post('/api/images', ['alt_text' => 'Test image', 'caption' => 'Caption'], $files);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test image', $data['data']['image']['alt_text']);
    }

//    public function testStoreWithCategories()
//    {
//        $category = ImageCategory::create(['name' => 'Photos', 'slug' => 'photos']);
//
//        $files = [
//            'image' => $this->createUploadedFile('avatar.jpg', 'image/jpeg')
//        ];
//
//        $response = $this->post('/api/images', ['alt_text' => 'Test', 'categories' => [$category->id]], $files);
//
//        $this->assertEquals(201, $response->getStatusCode());
//    }

    public function testStoreValidatesFileRequired()
    {
        $response = $this->post('/api/images', ['alt_text' => 'Test']);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testShowReturnsImage()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->get("/api/images/{$image->id}");
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
        $response = $this->put("/api/images/{$image->id}", ['alt_text' => 'Updated alt', 'caption' => 'New caption']);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated alt', $data['data']['image']['alt_text']);
    }

    public function testDestroyMovesToTrash()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->delete("/api/images/{$image->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('trash', $response->getContent());
    }

    public function testDestroyHardDelete()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->delete("/api/images/{$image->id}?hard_delete=true");
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
        $response = $this->get('/api/image-recent?limit=5');
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
        $response = $this->post('/api/image-track-usage', ['image_id' => $image->id, 'usable_type' => 'Page', 'usable_id' => 1, 'context' => 'featured']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRemoveUsage()
    {
        $image = Image::create(['url' => 'test', 'file_size' => 6, 'filename' => 'test.jpg', 'file_path' => '/uploads/test.jpg', 'size' => 1024, 'mime_type' => 'image/jpeg', 'original_name' => 'test.jpg']);;
        $response = $this->post('/api/image-remove-usage', ['image_id' => $image->id, 'usable_type' => 'Page', 'usable_id' => 1, 'context' => 'featured']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCategoriesReturnsAllCategories()
    {
        ImageCategory::create(['name' => 'Photos', 'slug' => 'photos']);
        ImageCategory::create(['name' => 'Graphics', 'slug' => 'graphics']);
        $response = $this->get('/api/image-categories');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']['categories']);
    }

    public function testCreateCategory()
    {
        $response = $this->post('/api/image-categories', ['name' => 'New Category', 'description' => 'Test']);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('New Category', $data['data']['category']['name']);
    }
}