<?php

namespace App\Tests\Unit\Services\Cms;

use App\Framework\FileUpload\CommandExecutorInterface;
use App\Framework\FileUpload\FileSystemInterface;
use App\Framework\Http\UploadedFile;
use App\Services\Cms\VideoUploadService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VideoUploadServiceTest extends TestCase
{
    private FileSystemInterface|MockObject $fileSystem;
    private CommandExecutorInterface|MockObject $commandExecutor;
    private VideoUploadService $service;

    protected function setUp(): void
    {
        $this->fileSystem = $this->createMock(FileSystemInterface::class);
        $this->commandExecutor = $this->createMock(CommandExecutorInterface::class);

        $this->service = new VideoUploadService(
            'uploads/videos',
            $this->fileSystem,
            $this->commandExecutor
        );

        $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');
    }

    public function testUploadValidVideo()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('video/mp4');
        $file->method('getSize')->willReturn(50 * 1024 * 1024);
        $file->method('getClientOriginalName')->willReturn('test.mp4');
        $file->method('getClientOriginalExtension')->willReturn('mp4');

        $file->method('moveTo')->willReturn(true);

        $result = $this->service->upload($file);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertEquals(50 * 1024 * 1024, $result['size']);
    }

    public function testUploadThrowsExceptionForInvalidFile()
    {
        $this->expectException(\Exception::class);

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(false);
        $file->method('getErrorMessage')->willReturn('Upload error');

        $this->service->upload($file);
    }

    public function testUploadThrowsExceptionForInvalidMimeType()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid file type');

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('video/jpeg');

        $this->service->upload($file);
    }

    public function testUploadThrowsExceptionForOversizedFile()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File size exceeds maximum');

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('video/mp4');
        $file->method('getSize')->willReturn(200 * 1024 * 1024); // 200MB

        $this->service->upload($file);
    }

    public function testDeleteVideo()
    {
        $this->fileSystem
            ->expects($this->once())
            ->method('glob')
            ->willReturn([
                '/path/to/thumbnails/video_thumb_1.jpg',
                '/path/to/thumbnails/video_thumb_2.jpg'
            ]);

        $this->fileSystem
            ->expects($this->exactly(3))
            ->method('fileExists')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->exactly(3))
            ->method('deleteFile')
            ->willReturn(true);

        $this->fileSystem
            ->method('pathinfo')
            ->willReturnCallback(function ($path, $flags) {
                if ($flags === PATHINFO_FILENAME) {
                    return 'video';
                }
                return pathinfo($path, $flags);
            });

        $result = $this->service->delete('2024-01-01/video.mp4');

        $this->assertTrue($result);
    }

    public function testDeleteVideoReturnsFalseWhenFileNotExists()
    {
        $this->fileSystem
            ->expects($this->once())
            ->method('glob')
            ->willReturn([]);

        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(false);

        $this->fileSystem
            ->method('pathinfo')
            ->willReturn('video');

        $result = $this->service->delete('2024-01-01/nonexistent.mp4');

        $this->assertFalse($result);
    }

    public function testDuplicateVideo()
    {
        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->with($this->stringContains('original.mp4'))
            ->willReturn(true);

        $this->fileSystem
            ->method('pathinfo')
            ->willReturnCallback(function ($path, $flags = PATHINFO_ALL) {
                $info = pathinfo($path);
                if ($flags === PATHINFO_ALL) {
                    return $info;
                }
                if ($flags === PATHINFO_FILENAME) {
                    return $info['filename'];
                }
                if ($flags === PATHINFO_EXTENSION) {
                    return $info['extension'] ?? '';
                }
                return $info;
            });

        $this->fileSystem
            ->expects($this->atLeast(1))
            ->method('realpath')
            ->willReturn('/var/www/html');

        $this->fileSystem
            ->expects($this->once())
            ->method('copy')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('fileSize')
            ->willReturn(50000000);

        $this->fileSystem
            ->method('isDirectory')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('glob')
            ->willReturn([]);

        $this->commandExecutor
            ->method('commandExists')
            ->willReturn(false);

        $result = $this->service->duplicate('2024-01-01/original.mp4');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertStringContainsString('copy', $result['filename']);
    }

    public function testDuplicateThrowsExceptionWhenOriginalNotExists()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Original video file does not exist');

        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(false);

        $this->fileSystem
            ->method('realpath')
            ->willReturn('/var/www/html');

        $this->service->duplicate('2024-01-01/nonexistent.mp4');
    }

    public function testGetVideoMetadataWithFFprobe()
    {
        $this->commandExecutor
            ->expects($this->once())
            ->method('commandExists')
            ->with('ffprobe')
            ->willReturn(true);

        // Mock shell_exec via method if possible, or test actual metadata extraction
        $result = $this->service->getVideoMetadata('/path/to/video.mp4');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
    }

    public function testGetVideoMetadataReturnsDefaultsWhenNoTools()
    {
        $this->commandExecutor
            ->expects($this->once())
            ->method('commandExists')
            ->with('ffprobe')
            ->willReturn(false);

        $result = $this->service->getVideoMetadata('/path/to/video.mp4');

        $this->assertEquals(0, $result['duration']);
        $this->assertNull($result['width']);
        $this->assertNull($result['height']);
    }

    public function testEnsureDirectoryExists()
    {
        $this->fileSystem
            ->expects($this->once())
            ->method('isDirectory')
            ->with('/path/to/dir')
            ->willReturn(false);

        $this->fileSystem
            ->expects($this->once())
            ->method('makeDirectory')
            ->with('/path/to/dir', 0755, true)
            ->willReturn(true);

        $this->service->ensureDirectoryExists('/path/to/dir');
    }

    public function testEnsureDirectoryExistsThrowsExceptionOnFailure()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create upload directory');

        $this->fileSystem
            ->expects($this->once())
            ->method('isDirectory')
            ->willReturn(false);

        $this->fileSystem
            ->expects($this->once())
            ->method('makeDirectory')
            ->willReturn(false);

        $this->service->ensureDirectoryExists('/path/to/dir');
    }

    public function testSettersAndGetters()
    {
        $this->assertEquals(
            [
                'video/mp4',
                'video/quicktime',
                'video/x-msvideo',
                'video/mpeg',
                'video/webm',
                'application/pdf',
                'application/zip',
                'application/x-zip-compressed'
            ],
            $this->service->getAllowedMimeTypes()
        );

        $this->service->setAllowedMimeTypes(['video/mp4']);
        $this->assertEquals(['video/mp4'], $this->service->getAllowedMimeTypes());

        $this->assertEquals(104857600, $this->service->getMaxFileSize());

        $this->service->setMaxFileSize(50000000);
        $this->assertEquals(50000000, $this->service->getMaxFileSize());

        $this->service->setThumbnailCount(3);
        $this->service->setThumbnailCount(15); // Should be capped at 10
    }

    public function testUploadContinuesWhenThumbnailGenerationFails()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('video/mp4');
        $file->method('getSize')->willReturn(50 * 1024 * 1024);
        $file->method('getClientOriginalName')->willReturn('test.mp4');
        $file->method('getClientOriginalExtension')->willReturn('mp4');
        $file->method('moveTo')->willReturn(true);

        // Mock ffmpeg not available
        $this->commandExecutor
            ->expects($this->once())
            ->method('commandExists')
            ->with('ffprobe')
            ->willReturn(false);

        $this->fileSystem
            ->method('realpath')
            ->willReturn('/var/www/html');

        $this->fileSystem
            ->method('isDirectory')
            ->willReturn(true);

        $result = $this->service->upload($file);

        // Upload should succeed even without thumbnails
        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('thumbnails', $result);
        $this->assertEmpty($result['thumbnails']); // No thumbnails generated
    }

    public function testGenerateThumbnailsReturnsEmptyArrayWhenFFmpegUnavailable()
    {
        $this->commandExecutor
            ->expects($this->once())
            ->method('commandExists')
            ->with('ffmpeg')
            ->willReturn(false);

        $result = $this->service->generateThumbnails('/path/to/video.mp4', 'video.mp4', 120);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGenerateThumbnailsReturnsEmptyArrayWhenDurationIsZero()
    {
        $result = $this->service->generateThumbnails('/path/to/video.mp4', 'video.mp4', 0);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testUploadValidWebMVideo()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('video/webm');
        $file->method('getSize')->willReturn(50 * 1024 * 1024);
        $file->method('getClientOriginalName')->willReturn('test.webm');
        $file->method('getClientOriginalExtension')->willReturn('webm');
        $file->method('moveTo')->willReturn(true);

        $result = $this->service->upload($file);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('filename', $result);
    }

    public function testUploadValidPDF()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('application/pdf');
        $file->method('getSize')->willReturn(5 * 1024 * 1024);
        $file->method('getClientOriginalName')->willReturn('document.pdf');
        $file->method('getClientOriginalExtension')->willReturn('pdf');
        $file->method('moveTo')->willReturn(true);

        $result = $this->service->upload($file);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['duration']);
        $this->assertNull($result['width']);
        $this->assertNull($result['height']);
        $this->assertEmpty($result['thumbnails']);
    }

    public function testUploadValidZIP()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('application/zip');
        $file->method('getSize')->willReturn(10 * 1024 * 1024);
        $file->method('getClientOriginalName')->willReturn('archive.zip');
        $file->method('getClientOriginalExtension')->willReturn('zip');
        $file->method('moveTo')->willReturn(true);

        $result = $this->service->upload($file);

        $this->assertIsArray($result);
        $this->assertEmpty($result['thumbnails']);
    }

}