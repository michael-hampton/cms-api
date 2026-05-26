<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Subscriptions\IssueDeliveryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class IssueDeliveryServiceTest extends FunctionalTestCase
{
    private $scheduleRepository;
    private $service;
    private $databaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduleRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->databaseMock       = Mockery::mock(Database::class);
        $this->service            = new IssueDeliveryService($this->scheduleRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Status management
    // =========================================================================

    public function testActivateSchedule(): void
    {
        $scheduleId = 1;
        $schedule   = Mockery::mock(IssueDelivery::class);

        $this->scheduleRepository->shouldReceive('update')
            ->with($scheduleId, ['status' => IssueScheduleStatus::ACTIVE->value])
            ->once()
            ->andReturn($schedule);

        $result = $this->service->activateSchedule($scheduleId);

        $this->assertSame($schedule, $result);
    }

    public function testCancelSchedule(): void
    {
        $scheduleId = 1;
        $schedule   = Mockery::mock(IssueDelivery::class);

        $this->scheduleRepository->shouldReceive('update')
            ->with($scheduleId, ['status' => IssueScheduleStatus::CANCELLED->value])
            ->once()
            ->andReturn($schedule);

        $result = $this->service->cancelSchedule($scheduleId);

        $this->assertSame($schedule, $result);
    }

    // =========================================================================
    // Cover image — removeCoverImage
    // =========================================================================

    public function testRemoveCoverImageDoesNothingWhenNoCoverImage(): void
    {
        $issue              = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->cover_image = null;

        // repository->update should NOT be called
        $this->scheduleRepository->shouldNotReceive('update');

        $this->service->removeCoverImage($issue);

        $this->assertTrue(true);
    }

    public function testRemoveCoverImageClearsFieldAndDeletesFileWhenImageExists(): void
    {
        // Create a real temp file to be "deleted"
        $tmpFile = tempnam(sys_get_temp_dir(), 'issue_cover_');
        file_put_contents($tmpFile, 'fake image data');

        $issue              = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id          = 42;
        $issue->cover_image = $tmpFile;

        $this->scheduleRepository->shouldReceive('update')
            ->with(42, ['cover_image' => null])
            ->once();

        $this->service->removeCoverImage($issue);

        // File should be gone from disk
        $this->assertFileDoesNotExist($tmpFile);
    }

    public function testRemoveCoverImageSkipsDiskDeletionWhenFileNotOnDisk(): void
    {
        $issue              = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id          = 99;
        // Path does not exist on disk
        $issue->cover_image = '/non/existent/path/image.jpg';

        // Repository update still fires even if the file is missing
        $this->scheduleRepository->shouldReceive('update')
            ->with(99, ['cover_image' => null])
            ->once();

        // Should not throw
        $this->service->removeCoverImage($issue);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Cover image — storeCoverImage
    // =========================================================================

    public function testStoreCoverImageRejectsOversizedFile(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/size|large|exceed/i');

        // 9 MB > 8 MB limit
        $file = $this->makeFakeUploadedFile(name: 'big.jpg', size: 9 * 1024 * 1024);

        $this->service->storeCoverImage($file);
    }

    public function testStoreCoverImageRejectsDisallowedExtension(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/type|extension|allowed/i');

        $file = $this->makeFakeUploadedFile(name: 'script.php', size: 1024);

        $this->service->storeCoverImage($file);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a minimal $_FILES-style array for a fake upload.
     * Does NOT use is_uploaded_file(), so FileUpload falls back to copy().
     */
    private function makeFakeUploadedFile(string $name, int $size): UploadedFile
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        // Write $size bytes of zeros
        file_put_contents($tmpFile, str_repeat("\0", $size));

        return new UploadedFile([
            'name'     => $name,
            'type'     => $this->mimeForName($name),
            'tmp_name' => $tmpFile,
            'error'    => UPLOAD_ERR_OK,
            'size'     => $size,
        ]);
    }

    private function mimeForName(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'application/octet-stream',
        };
    }
}