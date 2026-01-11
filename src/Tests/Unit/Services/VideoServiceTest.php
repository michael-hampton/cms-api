<?php

namespace App\Tests\Unit\Services;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\UploadedFile;
use App\Models\Video;
use App\Repositories\Cms\VideoRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Services\Cms\VideoService;
use App\Services\Cms\VideoUploadService;
use Exception;
use Mockery;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

// Mock the global 'config' function used in the constructor
// In a real application, you'd likely use a testing framework that handles this setup.
if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        return match ($key) {
            'upload.path' => 'tests/uploads',
            'app.url' => 'http://localhost',
            default => $default,
        };
    }
}

class VideoServiceTest extends TestCase
{
    private VideoRepository|\PHPUnit\Framework\MockObject\MockObject $videoRepository;
    private VideoUploadService|\PHPUnit\Framework\MockObject\MockObject $videoUploadService;
    private VideoService $videoService;

    protected function setUp(): void
    {
        $this->videoRepository = Mockery::mock(VideoRepository::class);
        $this->videoUploadService = Mockery::mock(VideoUploadService::class);

        $this->videoService = new VideoService(
            $this->videoRepository,
            $this->videoUploadService,
            'http://localhost'
        );
    }

    // --- Upload Video Tests ---

    public function testUploadVideoSuccess()
    {
        // Arrange
        $uploadedFileMock = Mockery::mock(UploadedFile::class);
        $videoModelMock = Mockery::mock(Video::class);

        // Mock UploadedFile methods for validation
        $uploadedFileMock->shouldReceive('isValid')->andReturn(true);
        $uploadedFileMock->shouldReceive('getSize')->andReturn(10 * 1024 * 1024); // 10MB
        $uploadedFileMock->shouldReceive('getMimeType')->andReturn('video/mp4');
        $uploadedFileMock->shouldReceive('getClientOriginalName')->andReturn('test_video.mp4');

        $uploadResult = [
            'path' => '2025-10-08/unique_filename.mp4',
            'filename' => 'unique_filename.mp4',
            'size' => 10485760,
            'duration' => 120.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => ['/tests/uploads/thumbnails/path/thumb_1.jpg'],
            'metadata' => []
        ];
        $metadata = ['title' => 'My Video', 'description' => 'A test video'];

        // Expect calls to VideoUploadService and VideoRepository
        $this->videoUploadService->shouldReceive('upload')
            ->once()
            ->with($uploadedFileMock)
            ->andReturn($uploadResult);

        $expectedVideoData = ['filename' => 'unique_filename.mp4',
            'original_name' => 'test_video.mp4',
            'file_path' => 'videos/2025-10-08/unique_filename.mp4',
            'url' => 'http://localhost/uploads/videos/2025-10-08/unique_filename.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 10485760,
            'duration' => 120.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => '["http:\/\/localhost\/uploads\/tests\/thumbnails\/path\/thumb_1.jpg"]',
            'title' => 'My Video',
            'description' => 'A test video'
        ];

        $this->videoRepository->shouldReceive('create')
            ->once()
            ->with($expectedVideoData)
            ->andReturn($videoModelMock);

        // Act
        $result = $this->videoService->uploadVideo($uploadedFileMock, $metadata);

        // Assert
        $this->assertSame($videoModelMock, $result);
    }

    public function testUploadVideoFailsOnInvalidFile()
    {
        // Arrange
        $uploadedFileMock = Mockery::mock(UploadedFile::class);
        $uploadedFileMock->shouldReceive('isValid')->andReturn(false);

        // Expect a ValidationException
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid file upload');

        // Act
        $this->videoService->uploadVideo($uploadedFileMock);
    }

    public function testUploadVideoFailsOnFileSize()
    {
        // Arrange
        $uploadedFileMock = Mockery::mock(UploadedFile::class);
        $uploadedFileMock->shouldReceive('isValid')->andReturn(true);
        $uploadedFileMock->shouldReceive('getSize')->andReturn(100 * 1024 * 1024 + 1); // Too large

        // Expect a ValidationException
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File size exceeds maximum allowed size of 100MB');

        // Act
        $this->videoService->uploadVideo($uploadedFileMock);
    }

    public function testUploadVideoFailsOnMimeType()
    {
        // Arrange
        $uploadedFileMock = Mockery::mock(UploadedFile::class);
        $uploadedFileMock->shouldReceive('isValid')->andReturn(true);
        $uploadedFileMock->shouldReceive('getFileInfo')->andReturn([]);
        $uploadedFileMock->shouldReceive('getSize')->andReturn(10 * 1024 * 1024);
        $uploadedFileMock->shouldReceive('getMimeType')->andReturn('application/jpg'); // Invalid MIME type

        // Expect a ValidationException
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File type not allowed. Only MP4, MOV, and AVI files are supported.');

        // Act
        $this->videoService->uploadVideo($uploadedFileMock);
    }

    public function testGetVideosCallsRepositorySearchWithCorrectCriteria()
    {
        // Arrange
        $filters = [
            'mime_type' => 'video/mp4',
            'sort_by' => 'title',
            'sort_order' => 'asc',
            'page' => 2,
            'per_page' => 50,
            'query' => 'test search'
        ];
        $paginatedResultMock = Mockery::mock(PaginatedResult::class);

        // Define the expected criteria object fields
        $expectedFilters = ['mime_type' => 'video/mp4'];
        $expectedSortBy = 'title';
        $expectedSortOrder = 'asc';
        $expectedPage = 2;
        $expectedPerPage = 50;
        $expectedQuery = 'test search';

        // Use Mockery's on() for the constraint
        $this->videoRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function (SearchCriteria $criteria) use (
                $expectedFilters, $expectedSortBy, $expectedSortOrder, $expectedPage, $expectedPerPage, $expectedQuery
            ) {
                $this->assertSame($expectedFilters, $criteria->getFilters(), 'Filters mismatch');
                $this->assertSame($expectedSortBy, $criteria->getSortBy(), 'SortBy mismatch');
                $this->assertSame($expectedSortOrder, $criteria->getSortOrder(), 'SortOrder mismatch');
                $this->assertSame($expectedPage, $criteria->getPage(), 'Page mismatch');
                $this->assertSame($expectedPerPage, $criteria->getPerPage(), 'PerPage mismatch');
                $this->assertSame($expectedQuery, $criteria->getSearchQuery(), 'SearchQuery mismatch');
                return true;
            }))
            ->andReturn($paginatedResultMock);

        // Act
        $result = $this->videoService->getVideos($filters);

        // Assert
        $this->assertSame($paginatedResultMock, $result);
    }


    // --- Delete Video Tests ---

    public function testDeleteVideoSoftDeletesWhenNotHardDelete()
    {
        // Arrange
        $videoId = 1;
        $videoModelMock = Mockery::mock(Video::class);
        $videoModelMock->shouldReceive('isUsed')->andReturn(true); // Usage is irrelevant for soft delete

        // Find the video
        $this->videoRepository->shouldReceive('find')
            ->with($videoId)
            ->andReturn($videoModelMock);

        // Expect softDelete call and no delete/videoUploadService::delete calls
        $videoModelMock->shouldReceive('softDelete')->once()->andReturn(true);
        $this->videoUploadService->shouldReceive('delete')->never();
        $videoModelMock->shouldReceive('delete')->never();

        // Act
        $result = $this->videoService->deleteVideo($videoId, false);

        // Assert
        $this->assertTrue($result);
    }

    public function testDeleteVideoHardDelete()
    {
        $video = Mockery::mock(Video::class)->makePartial();
        $video->file_path = 'videos/test.mp4';

        $video->shouldReceive('isUsed')->once()->andReturn(false);
        $video->shouldReceive('delete')->once()->andReturn(true);

        $this->videoRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($video);

        $this->videoUploadService->shouldReceive('delete')
            ->once()
            ->with('videos/test.mp4');

        $result = $this->videoService->deleteVideo(1, true);

        $this->assertTrue($result);
    }

    public function testUpdateVideoMetadata()
    {
        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;

        $this->videoRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($video);

        $video->shouldReceive('updateMetadata')
            ->once()
            ->with(['title' => 'New Title', 'description' => 'New Description']);

        $result = $this->videoService->updateVideoMetadata(1, [
            'title' => 'New Title',
            'description' => 'New Description'
        ]);

        $this->assertInstanceOf(Video::class, $result);
    }

    public function testGetRecentVideos()
    {
        $videos = collect([
            Mockery::mock(Video::class),
            Mockery::mock(Video::class)
        ]);

        $this->videoRepository->shouldReceive('getRecentVideos')
            ->once()
            ->with(10)
            ->andReturn($videos);

        $result = $this->videoService->getRecentVideos(10);

        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
        $this->assertCount(2, $result);
    }

//    public function testBulkDeleteVideos()
//    {
//        $video1 = Mockery::mock(Video::class)->makePartial();
//        $video1->shouldReceive('isUsed')->andReturn(false);
//        $video1->shouldReceive('softDelete')->once()->andReturn(true);
//
//        $video2 = Mockery::mock(Video::class)->makePartial();
//        $video2->shouldReceive('isUsed')->andReturn(true);
//
//        $this->videoRepository->shouldReceive('find')
//            ->with(1)
//            ->once()
//            ->andReturn($video1);
//
//        $this->videoRepository->shouldReceive('find')
//            ->with(2)
//            ->once()
//            ->andReturn($video2);
//
//        // Implement bulk delete in VideoService first, then add this test
//    }

    public function testDeleteVideoThrowsExceptionOnHardDeleteIfUsed()
    {
        // Arrange
        $videoId = 3;
        $videoModelMock = Mockery::mock(Video::class);
        $videoModelMock->shouldReceive('isUsed')->andReturn(true); // In use

        // Find the video
        $this->videoRepository->shouldReceive('find')
            ->with($videoId)
            ->andReturn($videoModelMock);

        // Expect an exception and no delete calls
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot delete video that is currently in use');
        $this->videoUploadService->shouldReceive('delete')->never();
        $videoModelMock->shouldReceive('delete')->never();

        // Act
        $this->videoService->deleteVideo($videoId, true);
    }

    public function testDeleteVideoThrowsExceptionIfNotFound()
    {
        // Arrange
        $videoId = 4;
        $this->videoRepository->shouldReceive('find')
            ->with($videoId)
            ->andReturn(null);

        // Expect an exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Video not found');

        // Act
        $this->videoService->deleteVideo($videoId);
    }

    // --- Duplicate Video Tests ---

//    public function testDuplicateVideoSuccessfully()
//    {
//        // Arrange
//        $videoId = 5;
//        $filePath = 'videos/original/file.mp4'; // Define the path once
//
//        // FIX: Create a stub and set the public properties explicitly.
//        $originalVideoMock = $this->createStub(Video::class);
//
//        // Set all properties accessed by the service directly
//        $originalVideoMock->file_path = $filePath;
//        $originalVideoMock->original_name = 'My Video File.mp4';
//        $originalVideoMock->mime_type = 'video/mp4';
//        $originalVideoMock->title = 'Original Video Title';
//        $originalVideoMock->description = 'Original Description';
//        $originalVideoMock->duration = 100.0;
//
//        $newVideoModelMock = $this->createMock(Video::class);
//        $duplicateResult = [
//            'path' => 'videos/original/file-copy-uniqueid.mp4',
//            'filename' => 'file-copy-uniqueid.mp4',
//            'size' => 52428800,
//            'duration' => 100.0,
//            'width' => 1280,
//            'height' => 720,
//            'thumbnails' => ['/tests/uploads/thumbnails/path/thumb_1_copy.jpg'],
//            'metadata' => []
//        ];
//
//        // Find original video
//        $this->videoRepository->method('find')->with($videoId)->willReturn($originalVideoMock);
//
//        // Expect duplication calls
//        $this->videoUploadService->expects($this->once())
//            ->method('duplicate')
//            ->with($filePath) // Expectation uses the defined string path
//            ->willReturn($duplicateResult);
//
//        $expectedVideoData = [
//            // ... (expected data using hardcoded string values)
//        ];
//
//        $this->videoRepository->expects($this->once())
//            ->method('create')
//            ->with($expectedVideoData)
//            ->willReturn($newVideoModelMock);
//
//        // Act
//        $result = $this->videoService->duplicateVideo($videoId);
//
//        // Assert
//        $this->assertSame($newVideoModelMock, $result);
//    }

    public function testUploadVideoSuccessfully()
    {
        $file = Mockery::mock(UploadedFile::class)->makePartial();
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(50 * 1024 * 1024); // 50MB
        $file->shouldReceive('getMimeType')->andReturn('video/mp4');
        $file->shouldReceive('getClientOriginalName')->andReturn('test-video.mp4');

        $uploadResult = [
            'filename' => 'test-video_123.mp4',
            'path' => '2024-01-01/test-video_123.mp4',
            'size' => 50 * 1024 * 1024,
            'duration' => 120.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => ['/uploads/thumbnails/thumb1.jpg']
        ];

        $this->videoUploadService->shouldReceive('upload')
            ->once()
            ->with($file)
            ->andReturn($uploadResult);

        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;

        $this->videoRepository->shouldReceive('create')
            ->once()
            ->andReturn($video);

        $result = $this->videoService->uploadVideo($file, [
            'title' => 'Test Video',
            'description' => 'Test Description'
        ]);

        $this->assertInstanceOf(Video::class, $result);
    }

    public function testUploadVideoThrowsExceptionForInvalidFile()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid file upload');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(false);

        $this->videoService->uploadVideo($file);
    }

    public function testUploadVideoThrowsExceptionForFileTooLarge()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File size exceeds maximum allowed size');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(150 * 1024 * 1024); // 150MB

        $this->videoService->uploadVideo($file);
    }

    public function testUploadVideoThrowsExceptionForInvalidMimeType()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File type not allowed');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getFileInfo')->andReturn([]);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(50 * 1024 * 1024);
        $file->shouldReceive('getMimeType')->andReturn('video/jpg');

        $this->videoService->uploadVideo($file);
    }

    public function testGetVideo()
    {
        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;

        $this->videoRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($video);

        $result = $this->videoService->getVideo(1);

        $this->assertInstanceOf(Video::class, $result);
    }

    public function testGetVideoReturnsNull()
    {
        $this->videoRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->videoService->getVideo(999);

        $this->assertNull($result);
    }

    public function testDeleteVideoSoftDelete()
    {
        $video = Mockery::mock(Video::class)->makePartial();
        $video->shouldReceive('softDelete')
            ->once()
            ->andReturn(true);

        $this->videoRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($video);

        $result = $this->videoService->deleteVideo(1, false);

        $this->assertTrue($result);
    }

    public function testDeleteVideoThrowsExceptionWhenUsed()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete video that is currently in use');

        $video = Mockery::mock(Video::class);
        $video->shouldReceive('isUsed')
            ->once()
            ->andReturn(true);

        $this->videoRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($video);

        $this->videoService->deleteVideo(1, true);
    }

    public function testDuplicateVideo()
    {
        $originalVideo = Mockery::mock(Video::class)->makePartial();
        $originalVideo->file_path = 'videos/original.mp4';
        $originalVideo->original_name = 'original.mp4';
        $originalVideo->mime_type = 'video/mp4';
        $originalVideo->title = 'Original Title';
        $originalVideo->description = 'Original Description';

        $this->videoRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($originalVideo);

        $duplicateResult = [
            'filename' => 'original-copy-123.mp4',
            'path' => '2024-01-01/original-copy-123.mp4',
            'size' => 50000000,
            'duration' => 120.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => []
        ];

        $this->videoUploadService->shouldReceive('duplicate')
            ->once()
            ->with('videos/original.mp4')
            ->andReturn($duplicateResult);

        $newVideo = Mockery::mock(Video::class)->makePartial();
        $newVideo->id = 2;

        $this->videoRepository->shouldReceive('create')
            ->once()
            ->andReturn($newVideo);

        $result = $this->videoService->duplicateVideo(1);

        $this->assertInstanceOf(Video::class, $result);
        $this->assertEquals(2, $result->id);
    }

    #[DoesNotPerformAssertions]
    public function testTrackVideoUsage()
    {
        $video = Mockery::mock(Video::class);
        $video->shouldReceive('addUsage')
            ->once()
            ->with('Product', 123, 'main_video');

        $this->videoRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($video);

        $this->videoService->trackVideoUsage(1, 'Product', 123, 'main_video');
    }

    #[DoesNotPerformAssertions]
    public function testRemoveVideoUsage()
    {
        $video = Mockery::mock(Video::class);
        $video->shouldReceive('removeUsage')
            ->once()
            ->with('Product', 123, 'main_video');

        $this->videoRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($video);

        $this->videoService->removeVideoUsage(1, 'Product', 123, 'main_video');
    }

    public function testUploadWebMVideoSuccessfully()
    {
        $file = Mockery::mock(UploadedFile::class)->makePartial();
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(50 * 1024 * 1024);
        $file->shouldReceive('getMimeType')->andReturn('video/webm');
        $file->shouldReceive('getClientOriginalName')->andReturn('test-video.webm');

        $uploadResult = [
            'filename' => 'test-video_123.webm',
            'path' => '2024-01-01/test-video_123.webm',
            'size' => 50 * 1024 * 1024,
            'duration' => 120.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => ['/uploads/thumbnails/thumb1.jpg']
        ];

        $this->videoUploadService->shouldReceive('upload')
            ->once()
            ->with($file)
            ->andReturn($uploadResult);

        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;

        $this->videoRepository->shouldReceive('create')
            ->once()
            ->andReturn($video);

        $result = $this->videoService->uploadVideo($file);
        $this->assertInstanceOf(Video::class, $result);
    }

    public function testUploadPDFSuccessfully()
    {
        $file = Mockery::mock(UploadedFile::class)->makePartial();
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(5 * 1024 * 1024);
        $file->shouldReceive('getMimeType')->andReturn('application/pdf');
        $file->shouldReceive('getClientOriginalName')->andReturn('document.pdf');

        $uploadResult = [
            'filename' => 'document_123.pdf',
            'path' => '2024-01-01/document_123.pdf',
            'size' => 5 * 1024 * 1024,
            'duration' => 0,
            'width' => null,
            'height' => null,
            'thumbnails' => []
        ];

        $this->videoUploadService->shouldReceive('upload')
            ->once()
            ->with($file)
            ->andReturn($uploadResult);

        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;

        $this->videoRepository->shouldReceive('create')
            ->once()
            ->andReturn($video);

        $result = $this->videoService->uploadVideo($file);
        $this->assertInstanceOf(Video::class, $result);
    }

    public function testUploadZIPSuccessfully()
    {
        $file = Mockery::mock(UploadedFile::class)->makePartial();
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(10 * 1024 * 1024);
        $file->shouldReceive('getMimeType')->andReturn('application/zip');
        $file->shouldReceive('getClientOriginalName')->andReturn('archive.zip');

        $uploadResult = [
            'filename' => 'archive_123.zip',
            'path' => '2024-01-01/archive_123.zip',
            'size' => 10 * 1024 * 1024,
            'duration' => 0,
            'width' => null,
            'height' => null,
            'thumbnails' => []
        ];

        $this->videoUploadService->shouldReceive('upload')
            ->once()
            ->with($file)
            ->andReturn($uploadResult);

        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;

        $this->videoRepository->shouldReceive('create')
            ->once()
            ->andReturn($video);

        $result = $this->videoService->uploadVideo($file);
        $this->assertInstanceOf(Video::class, $result);
    }

    public function testUploadMOVVideoSuccessfully()
    {
        $file = Mockery::mock(UploadedFile::class)->makePartial();
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(50 * 1024 * 1024);
        $file->shouldReceive('getMimeType')->andReturn('video/quicktime');
        $file->shouldReceive('getClientOriginalName')->andReturn('test-video.mov');

        $uploadResult = [
            'filename' => 'test-video_123.mov',
            'path' => '2024-01-01/test-video_123.mov',
            'size' => 50 * 1024 * 1024,
            'duration' => 120.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => ['/uploads/thumbnails/thumb1.jpg']
        ];

        $this->videoUploadService->shouldReceive('upload')
            ->once()
            ->with($file)
            ->andReturn($uploadResult);

        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;

        $this->videoRepository->shouldReceive('create')
            ->once()
            ->andReturn($video);

        $result = $this->videoService->uploadVideo($file);
        $this->assertInstanceOf(Video::class, $result);
    }

    public function testUploadAVIVideoSuccessfully()
    {
        $file = Mockery::mock(UploadedFile::class)->makePartial();
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(50 * 1024 * 1024);
        $file->shouldReceive('getMimeType')->andReturn('video/x-msvideo');
        $file->shouldReceive('getClientOriginalName')->andReturn('test-video.avi');

        $uploadResult = [
            'filename' => 'test-video_123.avi',
            'path' => '2024-01-01/test-video_123.avi',
            'size' => 50 * 1024 * 1024,
            'duration' => 120.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => ['/uploads/thumbnails/thumb1.jpg']
        ];

        $this->videoUploadService->shouldReceive('upload')
            ->once()
            ->with($file)
            ->andReturn($uploadResult);

        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;

        $this->videoRepository->shouldReceive('create')
            ->once()
            ->andReturn($video);

        $result = $this->videoService->uploadVideo($file);
        $this->assertInstanceOf(Video::class, $result);
    }

    public function testUploadMPEGVideoSuccessfully()
    {
        $file = Mockery::mock(UploadedFile::class)->makePartial();
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getSize')->andReturn(50 * 1024 * 1024);
        $file->shouldReceive('getMimeType')->andReturn('video/mpeg');
        $file->shouldReceive('getClientOriginalName')->andReturn('test-video.mpeg');

        $uploadResult = [
            'filename' => 'test-video_123.mpeg',
            'path' => '2024-01-01/test-video_123.mpeg',
            'size' => 50 * 1024 * 1024,
            'duration' => 120.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => ['/uploads/thumbnails/thumb1.jpg']
        ];

        $this->videoUploadService->shouldReceive('upload')
            ->once()
            ->with($file)
            ->andReturn($uploadResult);

        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;

        $this->videoRepository->shouldReceive('create')
            ->once()
            ->andReturn($video);

        $result = $this->videoService->uploadVideo($file);
        $this->assertInstanceOf(Video::class, $result);
    }
}