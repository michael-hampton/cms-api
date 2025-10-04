<?php

namespace App\Tests\Unit\Services;

use App\Framework\Http\UploadedFile;
use App\Services\ImageUploadService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ImageUploadServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageUploadService('uploads/test');
        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing'); // optional, for functions using getenv()
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testUploadThrowsExceptionForInvalidFile()
    {
        $this->expectException(\Exception::class);

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(false);
        $file->shouldReceive('getErrorMessage')->andReturn('Upload error');

        $this->service->upload($file);
    }

    public function testUploadThrowsExceptionForInvalidMimeType()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid file type');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getMimeType')->andReturn('application/pdf');

        $this->service->upload($file);
    }

    public function testUploadThrowsExceptionForOversizedFile()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File size exceeds');

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
        $file->shouldReceive('getSize')->andReturn(6 * 1024 * 1024); // 6MB

        $this->service->upload($file);
    }

    public function testUploadToPathGeneratesCorrectFilename()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getClientOriginalName')->andReturn('my-test-file.jpg');
        $file->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
        $file->shouldReceive('moveTo')->andReturn(true);

        $result = $this->service->uploadToPath($file, 'images/2025-01');

        $this->assertStringContainsString('images/2025-01/', $result);
        $this->assertStringEndsWith('.jpg', $result);
    }

    public function testSetAndGetAllowedMimeTypes()
    {
        $mimeTypes = ['image/jpeg', 'image/png'];
        $this->service->setAllowedMimeTypes($mimeTypes);

        $this->assertEquals($mimeTypes, $this->service->getAllowedMimeTypes());
    }

    public function testSetAndGetMaxFileSize()
    {
        $size = 1024 * 1024 * 5; // 5MB
        $this->service->setMaxFileSize($size);

        $this->assertEquals($size, $this->service->getMaxFileSize());
    }

    public function testDeleteHandlesBothAbsoluteAndRelativePaths()
    {
        // This test verifies the logic without actual file operations
        $this->expectNotToPerformAssertions();

        // Test with relative path
        $this->service->delete('images/test.jpg');

        // Test with absolute path
        $this->service->delete('/uploads/images/test.jpg');
    }
}