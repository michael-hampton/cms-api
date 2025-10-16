<?php

namespace App\Tests\Unit\Models;

use App\Models\Video;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class VideoModelTest extends FunctionalTestCase
{
    protected Video $video;

    protected function setUp(): void
    {
        parent::setUp();
        $this->video = new Video([
            'filename' => 'test-video.mp4',
            'original_name' => 'Test Video.mp4',
            'file_path' => '/videos/test-video.mp4',
            'url' => 'https://example.com/videos/test-video.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 10485760, // 10MB
            'duration' => 125.5,
            'width' => 1920,
            'height' => 1080,
            'thumbnails' => json_encode(['thumb1.jpg', 'thumb2.jpg']),
            'title' => 'Test Video',
            'description' => 'Test description',
            'site_id' => 1
        ]);
    }

    public function testVideoCanBeInstantiated()
    {
        $this->assertInstanceOf(Video::class, $this->video);
    }

    public function testVideoHasCorrectTableName()
    {
        $this->assertEquals('videos', $this->video->getTable());
    }

    public function testGetThumbnailsReturnsArrayFromJsonString()
    {
        $this->video->thumbnails = json_encode(['thumb1.jpg', 'thumb2.jpg']);
        $result = $this->video->getThumbnails();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('thumb1.jpg', $result[0]);
    }

    public function testGetThumbnailsReturnsArrayFromArray()
    {
        $this->video->thumbnails = ['thumb1.jpg', 'thumb2.jpg'];
        $result = $this->video->getThumbnails();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetThumbnailsReturnsEmptyArrayWhenNull()
    {
        $this->video->thumbnails = null;
        $result = $this->video->getThumbnails();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testUpdateMetadataUpdatesAllowedFields()
    {
        $metadata = [
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'forbidden_field' => 'should not update'
        ];

        // Mock the update method
        $video = $this->getMockBuilder(Video::class)
            ->onlyMethods(['update'])
            ->getMock();

        $video->expects($this->once())
            ->method('update')
            ->with([
                'title' => 'Updated Title',
                'description' => 'Updated Description'
            ])
            ->willReturn(true);

        $result = $video->updateMetadata($metadata);
        $this->assertTrue($result);
    }

    public function testUpdateMetadataReturnsTrueWhenNoValidFields()
    {
        $metadata = ['forbidden_field' => 'value'];
        $result = $this->video->updateMetadata($metadata);

        $this->assertTrue($result);
    }

    public function testIsUsedReturnsFalse()
    {
        $this->assertFalse($this->video->isUsed());
    }

    public function testGetFormattedDurationFormatsSeconds()
    {
        $this->video->duration = 65.0;
        $result = $this->video->getFormattedDuration();

        $this->assertEquals('1:05', $result);
    }

    public function testGetFormattedDurationFormatsMinutes()
    {
        $this->video->duration = 125.5;
        $result = $this->video->getFormattedDuration();

        $this->assertEquals('2:05', $result);
    }

    public function testGetFormattedDurationHandlesZero()
    {
        $this->video->duration = 0;
        $result = $this->video->getFormattedDuration();

        $this->assertEquals('0:00', $result);
    }

    public function testGetFormattedSizeFormatsBytes()
    {
        $this->video->file_size = 500;
        $result = $this->video->getFormattedSize();

        $this->assertEquals('500 bytes', $result);
    }

    public function testGetFormattedSizeFormatsKilobytes()
    {
        $this->video->file_size = 2048;
        $result = $this->video->getFormattedSize();

        $this->assertEquals('2.00 KB', $result);
    }

    public function testGetFormattedSizeFormatsMegabytes()
    {
        $this->video->file_size = 10485760; // 10MB
        $result = $this->video->getFormattedSize();

        $this->assertEquals('10.00 MB', $result);
    }

    public function testGetFormattedSizeFormatsGigabytes()
    {
        $this->video->file_size = 2147483648; // 2GB
        $result = $this->video->getFormattedSize();

        $this->assertEquals('2.00 GB', $result);
    }

    // Attribute Getter/Setter Tests
    public function testSetAndGetFilename()
    {
        $this->video->filename = 'new-video.mp4';
        $this->assertEquals('new-video.mp4', $this->video->filename);
    }

    public function testSetAndGetOriginalName()
    {
        $this->video->original_name = 'New Video.mp4';
        $this->assertEquals('New Video.mp4', $this->video->original_name);
    }

    public function testSetAndGetFilePath()
    {
        $this->video->file_path = '/new/path/video.mp4';
        $this->assertEquals('/new/path/video.mp4', $this->video->file_path);
    }

    public function testSetAndGetUrl()
    {
        $this->video->url = 'https://newdomain.com/video.mp4';
        $this->assertEquals('https://newdomain.com/video.mp4', $this->video->url);
    }

    public function testSetAndGetMimeType()
    {
        $this->video->mime_type = 'video/webm';
        $this->assertEquals('video/webm', $this->video->mime_type);
    }

    public function testSetAndGetFileSize()
    {
        $this->video->file_size = 20971520; // 20MB
        $this->assertEquals(20971520, $this->video->file_size);
    }

    public function testSetAndGetDuration()
    {
        $this->video->duration = 300.5;
        $this->assertEquals(300.5, $this->video->duration);
    }

    public function testSetAndGetWidth()
    {
        $this->video->width = 3840;
        $this->assertEquals(3840, $this->video->width);
    }

    public function testSetAndGetHeight()
    {
        $this->video->height = 2160;
        $this->assertEquals(2160, $this->video->height);
    }

    public function testSetAndGetThumbnails()
    {
        $thumbnails = ['thumb3.jpg', 'thumb4.jpg'];
        $this->video->thumbnails = $thumbnails;
        $this->assertEquals($thumbnails, $this->video->thumbnails);
    }

    public function testSetAndGetTitle()
    {
        $this->video->title = 'New Video Title';
        $this->assertEquals('New Video Title', $this->video->title);
    }

    public function testSetAndGetDescription()
    {
        $this->video->description = 'New description';
        $this->assertEquals('New description', $this->video->description);
    }

    public function testSetAndGetSiteId()
    {
        $this->video->site_id = 5;
        $this->assertEquals(5, $this->video->site_id);
    }
    public function testUpdateMetadataOnlyUpdatesSpecifiedFields()
    {
        $metadata = ['title' => 'Only Title'];

        $video = $this->getMockBuilder(Video::class)
            ->onlyMethods(['update'])
            ->getMock();

        $video->expects($this->once())
            ->method('update')
            ->with(['title' => 'Only Title'])
            ->willReturn(true);

        $result = $video->updateMetadata($metadata);
        $this->assertTrue($result);
    }

    public function testGetFormattedDurationHandlesLongDurations()
    {
        $this->video->duration = 3665; // 1 hour, 1 minute, 5 seconds
        $result = $this->video->getFormattedDuration();

        $this->assertEquals('61:05', $result);
    }

    public function testThumbnailsAreCastedToJson()
    {
        $thumbnails = ['thumb1.jpg', 'thumb2.jpg', 'thumb3.jpg'];
        $this->video->thumbnails = $thumbnails;

        $retrieved = $this->video->thumbnails;
        $this->assertIsArray($retrieved);
        $this->assertEquals($thumbnails, $retrieved);
    }

    public function testNumericAttributesAreCastedCorrectly()
    {
        $this->video->file_size = '10485760';
        $this->assertIsInt($this->video->file_size);

        $this->video->duration = '125.5';
        $this->assertIsFloat($this->video->duration);

        $this->video->width = '1920';
        $this->assertIsInt($this->video->width);

        $this->video->height = '1080';
        $this->assertIsInt($this->video->height);
    }

    public function testToArrayIncludesAllAttributes()
    {
        $array = $this->video->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('filename', $array);
        $this->assertArrayHasKey('original_name', $array);
        $this->assertArrayHasKey('file_path', $array);
        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('mime_type', $array);
        $this->assertArrayHasKey('file_size', $array);
        $this->assertArrayHasKey('duration', $array);
        $this->assertArrayHasKey('width', $array);
        $this->assertArrayHasKey('height', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
    }

    public function testCreateVideo()
    {
        $video = Video::create([
            'filename' => 'demo-video.mp4',
            'original_name' => 'Demo Video.mp4',
            'file_path' => '/videos/demo-video.mp4',
            'url' => 'https://example.com/videos/demo-video.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 5242880,
            'duration' => 60.0,
            'width' => 1280,
            'height' => 720,
            'site_id' => 1
        ]);

        $this->assertInstanceOf(Video::class, $video);
        $this->assertEquals('demo-video.mp4', $video->filename);
        $this->assertEquals(1280, $video->width);
        $this->assertEquals(720, $video->height);
    }

    public function testFillMethodPopulatesAttributes()
    {
        $video = new Video();
        $video->fill([
            'filename' => 'new-video.mp4',
            'title' => 'New Video',
            'duration' => 120.5
        ]);

        $this->assertEquals('new-video.mp4', $video->filename);
        $this->assertEquals('New Video', $video->title);
        $this->assertEquals(120.5, $video->duration);
    }

    public function testSoftDeleteSetsDeletedAt()
    {
        $video = $this->getMockBuilder(Video::class)
            ->onlyMethods(['update'])
            ->getMock();

        $video->expects($this->once())
            ->method('update')
            ->with($this->callback(function($data) {
                return isset($data['deleted_at']) && !empty($data['deleted_at']);
            }))
            ->willReturn(true);

        $result = $video->softDelete();
        $this->assertTrue($result);
    }

    public function testGetFormattedDurationWithHours()
    {
        $this->video->duration = 7325; // 2 hours, 2 minutes, 5 seconds
        $result = $this->video->getFormattedDuration();

        $this->assertEquals('122:05', $result);
    }

    public function testGetFormattedSizeEdgeCases()
    {
        // Test 0 bytes
        $this->video->file_size = 0;
        $this->assertEquals('0 bytes', $this->video->getFormattedSize());

        // Test exactly 1 KB
        $this->video->file_size = 1024;
        $this->assertEquals('1.00 KB', $this->video->getFormattedSize());

        // Test exactly 1 MB
        $this->video->file_size = 1048576;
        $this->assertEquals('1.00 MB', $this->video->getFormattedSize());

        // Test exactly 1 GB
        $this->video->file_size = 1073741824;
        $this->assertEquals('1.00 GB', $this->video->getFormattedSize());
    }
}