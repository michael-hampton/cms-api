<?php

namespace App\Tests\Functional\Controllers;

use App\Framework\Authorization\Auth;
use App\Framework\Session\Session;
use App\Models\Video;

class VideoControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupTestVideos();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestVideos();
        parent::tearDown();
    }

    private function cleanupTestVideos(): void
    {
        // Clean up test videos from database
        $this->database->query("DELETE FROM videos WHERE site_id = ?", [$this->siteId]);
    }

    public function testIndexReturnsVideosWithPagination()
    {
        // Create test videos
        Video::create([
            'filename' => 'test1.mp4',
            'original_name' => 'Test Video 1.mp4',
            'file_path' => 'videos/test1.mp4',
            'url' => 'http://localhost/uploads/videos/test1.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 1000000,
            'duration' => 60,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => json_encode([]),
            'site_id' => $this->siteId
        ]);

        Video::create([
            'filename' => 'test2.mp4',
            'original_name' => 'Test Video 2.mp4',
            'file_path' => 'videos/test2.mp4',
            'url' => 'http://localhost/uploads/videos/test2.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 2000000,
            'duration' => 120,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => json_encode([]),
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/videos');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('items', $data['data']);
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertCount(2, $data['data']['items']);
        $this->assertEquals(2, $data['data']['pagination']['total']);
    }

    public function testIndexWithQueryParameter()
    {
        Video::create([
            'filename' => 'searchable.mp4',
            'original_name' => 'Searchable Video.mp4',
            'file_path' => 'videos/searchable.mp4',
            'url' => 'http://localhost/uploads/videos/searchable.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 1000000,
            'duration' => 60,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => json_encode([]),
            'title' => 'Searchable Title',
            'site_id' => $this->siteId
        ]);

        Video::create([
            'filename' => 'other.mp4',
            'original_name' => 'Other Video.mp4',
            'file_path' => 'videos/other.mp4',
            'url' => 'http://localhost/uploads/videos/other.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 1000000,
            'duration' => 60,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => json_encode([]),
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/videos?q=Searchable');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['items']);
        $this->assertEquals('Searchable Title', $data['data']['items'][0]['title']);
    }

    public function testIndexWithPaginationParameters()
    {
        // Create multiple videos
        for ($i = 1; $i <= 25; $i++) {
            Video::create([
                'filename' => "test{$i}.mp4",
                'original_name' => "Test Video {$i}.mp4",
                'file_path' => "videos/test{$i}.mp4",
                'url' => "http://localhost/uploads/videos/test{$i}.mp4",
                'mime_type' => 'video/mp4',
                'file_size' => 1000000,
                'duration' => 60,
                'width' => 1920,
                'height' => 1080,
                'thumbnails' => json_encode([]),
                'site_id' => $this->siteId
            ]);
        }

        $response = $this->getForSite('/api/videos?page=2&per_page=10');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(10, $data['data']['items']);
        $this->assertEquals(25, $data['data']['pagination']['total']);
        $this->assertEquals(2, $data['data']['pagination']['page']);
        $this->assertEquals(10, $data['data']['pagination']['per_page']);
        $this->assertEquals(3, $data['data']['pagination']['total_pages']);
        $this->assertTrue($data['data']['pagination']['has_more']);
    }

    public function testUploadVideoSuccessfully()
    {
        $_ENV['APP_ENV'] = 'testing';

        // Create a mock video file
        $videoFile = $this->createVideoFile('test-upload.mp4', 'video/mp4');

        $response = $this->postForSite('/api/videos', [
            'title' => 'My Test Video',
            'description' => 'This is a test video'
        ], [
            'video' => $videoFile
        ]);

        $this->assertResponseStatus(201, $response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('name', $data['data']);
        $this->assertArrayHasKey('url', $data['data']);
        $this->assertEquals('test-upload.mp4', $data['data']['name']);
    }

    public function testUploadVideoWithoutFile()
    {
        $response = $this->postForSite('/api/videos', [
            'title' => 'My Test Video'
        ]);

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('error', $data['data']);
        $this->assertStringContainsString('No valid video file', $data['data']['error']);
    }

    public function testUploadVideoWithInvalidFileType()
    {
        $_ENV['APP_ENV'] = 'production'; // Force actual validation

        $imageFile = $this->createVideoFile('test.jpg', 'image/jpeg');

        $response = $this->postForSite('/api/videos', [], [
            'video' => $imageFile
        ]);

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsString('No valid video file provided', $data['data']['error']);
    }

    public function testShowVideoSuccessfully()
    {
        $video = Video::create([
            'filename' => 'show-test.mp4',
            'original_name' => 'Show Test Video.mp4',
            'file_path' => 'videos/show-test.mp4',
            'url' => 'http://localhost/uploads/videos/show-test.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 1000000,
            'duration' => 60.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => json_encode(['/uploads/thumb1.jpg']),
            'title' => 'Show Test',
            'description' => 'Test description',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/videos/{$video->id}");

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($video->id, $data['data']['id']);
        $this->assertEquals('Show Test Video.mp4', $data['data']['name']);
        $this->assertEquals('http://localhost/uploads/videos/show-test.mp4', $data['data']['url']);
        $this->assertEquals(1000000, $data['data']['size']);
        $this->assertEquals(60.5, $data['data']['duration']);
        $this->assertEquals(1920, $data['data']['width']);
        $this->assertEquals(1080, $data['data']['height']);
        $this->assertEquals('Show Test', $data['data']['title']);
        $this->assertEquals('Test description', $data['data']['description']);
        $this->assertIsArray($data['data']['thumbnails']);
    }

    public function testShowVideoNotFound()
    {
        $response = $this->getForSite('/api/videos/99999');

        $this->assertResponseStatus(404, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('error', $data['data']);
        $this->assertEquals('Video not found', $data['data']['error']);
    }

    public function testDeleteVideoSuccessfully()
    {
        $video = Video::create([
            'filename' => 'delete-test.mp4',
            'original_name' => 'Delete Test Video.mp4',
            'file_path' => 'videos/delete-test.mp4',
            'url' => 'http://localhost/uploads/videos/delete-test.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 1000000,
            'duration' => 60,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => json_encode([]),
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite("/api/videos/{$video->id}");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('message', $data['data']);
        $this->assertEquals('Video deleted successfully', $data['data']['message']);

        // Verify soft delete
        $deletedVideo = Video::withTrashed()->find($video->id);

        $this->assertNotNull($deletedVideo->deleted_at);
    }

    public function testDeleteVideoNotFound()
    {
        $response = $this->deleteForSite('/api/videos/99999');

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsString('not found', $data['data']['error']);
    }

    public function testDeleteVideoHardDelete()
    {
        // Create a temporary physical file
        $filePath = $this->createTempUploadFile('videos/hard-delete-test.mp4', 'test video content');

        $video = Video::create([
            'filename' => 'hard-delete-test.mp4',
            'original_name' => 'Hard Delete Test.mp4',
            'file_path' => 'videos/hard-delete-test.mp4',
            'url' => 'http://localhost/uploads/videos/hard-delete-test.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 1000000,
            'duration' => 60,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => json_encode([]),
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite("/api/videos/{$video->id}?hard_delete=1");

        $this->assertResponseOk($response);

        // Verify hard delete
        $deletedVideo = Video::find($video->id);
        $this->assertNull($deletedVideo);
    }

    private function createVideoFile(string $filename, string $mimeType): array
    {
        // Create minimal valid video-like content
        $content = 'MOCK_VIDEO_CONTENT';

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_video_');
        file_put_contents($tmpFile, $content);

        return [
            'name' => $filename,
            'type' => $mimeType,
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($content),
            'content' => $content
        ];
    }
}