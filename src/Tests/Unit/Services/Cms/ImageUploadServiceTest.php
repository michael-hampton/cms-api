<?php

namespace App\Tests\Unit\Services\Cms;

use App\Framework\FileUpload\FileSystemInterface;
use App\Framework\Http\UploadedFile;
use App\Services\Cms\ImageUploadService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImageUploadServiceTest extends TestCase
{
    private FileSystemInterface|MockObject $fileSystem;
    private ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = $this->createMock(FileSystemInterface::class);
        $this->service = new ImageUploadService('uploads/test', $this->fileSystem);

        $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');
    }

    public function testUploadThrowsExceptionForInvalidFile()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Upload error');

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
        $file->method('getMimeType')->willReturn('application/pdf');

        $this->service->upload($file);
    }

    public function testUploadThrowsExceptionForOversizedFile()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File size exceeds');

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getSize')->willReturn(6 * 1024 * 1024); // 6MB

        $this->service->upload($file);
    }

    public function testUploadSuccessfullyInTestingMode()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getSize')->willReturn(1024 * 1024); // 1MB
        $file->method('getClientOriginalExtension')->willReturn('jpg');

        $file->method('moveTo')->willReturn(true);

        $result = $this->service->upload($file);

        $this->assertStringStartsWith('/uploads/test/', $result);
        $this->assertStringEndsWith('.jpg', $result);
        $this->assertStringContainsString('author_', $result);
    }

    public function testUploadDeletesOldImageInProductionMode()
    {
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['DOCUMENT_ROOT'] = '/var/www/html';

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getSize')->willReturn(1024 * 1024);
        $file->method('getClientOriginalExtension')->willReturn('jpg');
        $file->method('moveTo')->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('isDirectory')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->with('/var/www/html/old-image.jpg')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('deleteFile')
            ->with('/var/www/html/old-image.jpg')
            ->willReturn(true);

        $result = $this->service->upload($file, '/old-image.jpg');

        $this->assertStringStartsWith('/uploads/test/', $result);
    }

    public function testUploadToPathValidatesFile()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid file type');

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('text/plain');

        $this->service->uploadToPath($file, 'images/2025-01');
    }

    public function testUploadToPathGeneratesCorrectFilename()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getSize')->willReturn(1024 * 1024);
        $file->method('getClientOriginalName')->willReturn('my-test-file.jpg');
        $file->method('getClientOriginalExtension')->willReturn('jpg');

        $file->method('moveTo')->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('isDirectory')
            ->willReturn(true);

        $result = $this->service->uploadToPath($file, 'images/2025-01');

        $this->assertStringStartsWith('images/2025-01/', $result);
        $this->assertStringEndsWith('.jpg', $result);
        //$this->assertStringContainsString('mytestfile', $result); // sanitized filename
    }

    public function testUploadToPathCreatesDirectoryIfNotExists()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('image/png');
        $file->method('getSize')->willReturn(500000);
        $file->method('getClientOriginalName')->willReturn('test.png');
        $file->method('getClientOriginalExtension')->willReturn('png');

        $file->method('moveTo')->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('isDirectory')
            ->willReturn(false);

        $this->fileSystem
            ->expects($this->once())
            ->method('makeDirectory')
            ->with($this->anything(), 0755, true)
            ->willReturn(true);

        $result = $this->service->uploadToPath($file, 'images/new-folder');

        $this->assertStringStartsWith('images/new-folder/', $result);
    }

    public function testUploadToPathDeletesOldImage()
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getSize')->willReturn(1024 * 1024);
        $file->method('getClientOriginalName')->willReturn('new.jpg');
        $file->method('getClientOriginalExtension')->willReturn('jpg');

        $file->method('moveTo')->willReturn(true);

        $this->fileSystem
            ->method('isDirectory')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('deleteFile')
            ->willReturn(true);

        $result = $this->service->uploadToPath($file, 'images/2025-01', 'images/old.jpg');

        $this->assertStringStartsWith('images/2025-01/', $result);
    }

    public function testDeleteWithAbsolutePath()
    {
        $_SERVER['DOCUMENT_ROOT'] = '/var/www/html';

        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->with('/var/www/html/uploads/test.jpg')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('deleteFile')
            ->with('/var/www/html/uploads/test.jpg')
            ->willReturn(true);

        $result = $this->service->delete('/uploads/test.jpg');

        $this->assertTrue($result);
    }

    public function testDeleteWithRelativePath()
    {
        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->with($this->stringContains('images/test.jpg'))
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('deleteFile')
            ->with($this->stringContains('images/test.jpg'))
            ->willReturn(true);

        $result = $this->service->delete('images/test.jpg');

        $this->assertTrue($result);
    }

    public function testDeleteReturnsFalseWhenFileNotExists()
    {
        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(false);

        $this->fileSystem
            ->expects($this->never())
            ->method('deleteFile');

        $result = $this->service->delete('images/nonexistent.jpg');

        $this->assertFalse($result);
    }

    public function testDuplicateSuccessfully()
    {
        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(true);

        $this->fileSystem
            ->method('pathinfo')
            ->willReturnCallback(function($path, $flags = PATHINFO_ALL) {
                $info = pathinfo($path);
                if ($flags === PATHINFO_ALL) {
                    return $info;
                }
                return $info;
            });

        $this->fileSystem
            ->expects($this->atLeast(1))
            ->method('dirname')
            ->willReturn('/var/www/html');

        $this->fileSystem
            ->method('isDirectory')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('copy')
            ->willReturn(true);

        $result = $this->service->duplicate('images/original.jpg');

        $this->assertStringStartsWith('images/', $result);
        $this->assertStringContainsString('copy', $result);
        $this->assertStringEndsWith('.jpg', $result);
    }

    public function testDuplicateThrowsExceptionWhenOriginalNotExists()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Original file does not exist');

        $this->fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(false);

        $this->fileSystem
            ->method('realpath')
            ->willReturn('/var/www/html');

        $this->service->duplicate('images/nonexistent.jpg');
    }

    public function testDuplicateThrowsExceptionWhenCopyFails()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to duplicate file');

        $this->fileSystem
            ->method('fileExists')
            ->willReturn(true);

        $this->fileSystem
            ->method('pathinfo')
            ->willReturn([
                'dirname' => 'images',
                'filename' => 'test',
                'extension' => 'jpg'
            ]);

        $this->fileSystem
            ->method('realpath')
            ->willReturn('/var/www/html');

        $this->fileSystem
            ->method('isDirectory')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->once())
            ->method('copy')
            ->willReturn(false);

        $this->service->duplicate('images/test.jpg');
    }

    public function testEnsureDirectoryExistsCreatesDirectory()
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

    public function testEnsureDirectoryExistsDoesNothingWhenExists()
    {
        $this->fileSystem
            ->expects($this->once())
            ->method('isDirectory')
            ->willReturn(true);

        $this->fileSystem
            ->expects($this->never())
            ->method('makeDirectory');

        $this->service->ensureDirectoryExists('/path/to/existing');
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

    public function testSetAndGetAllowedMimeTypes()
    {
        $mimeTypes = ['image/jpeg', 'image/png'];
        $this->service->setAllowedMimeTypes($mimeTypes);

        $this->assertEquals($mimeTypes, $this->service->getAllowedMimeTypes());
    }

    public function testSetAndGetMaxFileSize()
    {
        $size = 1024 * 1024 * 10; // 10MB
        $this->service->setMaxFileSize($size);

        $this->assertEquals($size, $this->service->getMaxFileSize());
    }

    public function testChainableSetters()
    {
        $result = $this->service
            ->setAllowedMimeTypes(['image/jpeg'])
            ->setMaxFileSize(1000000);

        $this->assertInstanceOf(ImageUploadService::class, $result);
        $this->assertEquals(['image/jpeg'], $this->service->getAllowedMimeTypes());
        $this->assertEquals(1000000, $this->service->getMaxFileSize());
    }
}