<?php

namespace App\Tests\Unit\Models;

use App\Models\Image;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ImageModelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testCreateImage()
    {
        $image = Image::create([
            'filename' => 'test-image.jpg',
            'original_name' => 'Test Image.jpg',
            'url' => 'https://example.com/test-image.jpg',
            'file_path' => '/uploads/2024/01/test-image.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 102400,
            'width' => 1920,
            'height' => 1080,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertEquals('test-image.jpg', $image->filename);
    }

    public function testIntegerCasts()
    {
        $image = Image::create([
            'filename' => 'test.jpg',
            'original_name' => 'test.jpg',
            'file_path' => '/uploads/test.jpg',
            'url' => 'https://example.com/test-image.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => '102400',
            'width' => '1920',
            'height' => '1080',
            'is_active' => true,
        ]);

        $this->assertIsInt($image->file_size);
        $this->assertIsInt($image->width);
        $this->assertIsInt($image->height);
    }

    public function testScopeActive()
    {
        Image::create(['filename' => 'active.jpg', 'original_name' => 'active.jpg', 'file_path' => '/active.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 1000, 'is_active' => true,             'url' => 'https://example.com/test-image.jpg',]);
        Image::create(['filename' => 'inactive.jpg', 'original_name' => 'inactive.jpg', 'file_path' => '/inactive.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 1000, 'is_active' => false,             'url' => 'https://example.com/test-image.jpg',]);

        $active = Image::active()->get();
        $this->assertCount(1, $active);
    }

    public function testScopeByMimeType()
    {
        Image::create(['filename' => 'image.jpg', 'original_name' => 'image.jpg', 'file_path' => '/image.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 1000, 'is_active' => true,             'url' => 'https://example.com/test-image.jpg',]);
        Image::create(['filename' => 'document.pdf', 'original_name' => 'document.pdf', 'file_path' => '/document.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1000, 'is_active' => true,             'url' => 'https://example.com/test-image.jpg',]);

        $images = Image::byMimeType('image')->get();
        $this->assertCount(1, $images);
    }

    public function testScopeBySize()
    {
        Image::create(['filename' => 'small.jpg', 'original_name' => 'small.jpg', 'file_path' => '/small.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 1000, 'is_active' => true,             'url' => 'https://example.com/test-image.jpg',]);
        Image::create(['filename' => 'medium.jpg', 'original_name' => 'medium.jpg', 'file_path' => '/medium.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 50000, 'is_active' => true,             'url' => 'https://example.com/test-image.jpg',]);
        Image::create(['filename' => 'large.jpg', 'original_name' => 'large.jpg', 'file_path' => '/large.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 100000, 'is_active' => true,             'url' => 'https://example.com/test-image.jpg',]);

        $medium = Image::bySize(10000, 60000)->get();
        $this->assertCount(1, $medium);
        $this->assertEquals('medium.jpg', $medium->first()->filename);
    }

    public function testGetDimensionsAttribute()
    {
        $image = Image::create([
            'filename' => 'test.jpg',
            'original_name' => 'test.jpg',
            'file_path' => '/test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'width' => 1920,
            'height' => 1080,
            'url' => 'https://example.com/test-image.jpg',
            'is_active' => true,
        ]);

        $this->assertEquals('1920x1080', $image->getDimensionsAttribute());
    }

    public function testGetIsImageAttribute()
    {
        $image = Image::create([
            'filename' => 'image.jpg',
            'original_name' => 'image.jpg',
            'file_path' => '/image.jpg',
            'url' => 'https://example.com/test-image.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'is_active' => true,
        ]);

        $this->assertTrue($image->getIsImageAttribute());
    }

    public function testUpdateMetadata()
    {
        $image = Image::create([
            'filename' => 'test.jpg',
            'original_name' => 'test.jpg',
            'url' => 'https://example.com/test-image.jpg',
            'file_path' => '/test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'is_active' => true,
        ]);

        $result = $image->updateMetadata([
            'alt_text' => 'Test alt text',
            'caption' => 'Test caption',
            'description' => 'Test description',
        ]);

        $this->assertTrue($result);
        $fresh = Image::find($image->id);
        $this->assertEquals('Test alt text', $fresh->alt_text);
    }

    public function testSoftDelete()
    {
        $image = Image::create([
            'filename' => 'test.jpg',
            'original_name' => 'test.jpg',
            'url' => 'https://example.com/test-image.jpg',
            'file_path' => '/test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'is_active' => true,
        ]);

        $result = $image->softDelete();
        $this->assertTrue($result);
    }

    public function testRestore()
    {
        $image = Image::create([
            'filename' => 'test.jpg',
            'original_name' => 'test.jpg',
            'file_path' => '/test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'url' => 'https://example.com/test-image.jpg',
            'is_active' => false,
        ]);

        $result = $image->restore();
        $this->assertTrue($result);

        $fresh = Image::find($image->id);
        $this->assertTrue($fresh->is_active);
    }
}