<?php

namespace App\Tests\Unit\Services\Billing;

use App\Enums\AttachmentableType;
use App\Framework\Database\Database;
use App\Framework\FileUpload\FileSystem;
use App\Framework\Http\UploadedFile;
use App\Models\Attachment;
use App\Repositories\Billing\AttachmentRepository;
use App\Services\Billing\AttachmentService;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class AttachmentServiceTest extends TestCase
{
    private AttachmentRepository&MockInterface $attachmentRepository;
    private FileSystem&MockInterface           $fileSystem;
    private Database&MockInterface             $database;
    private AttachmentService                  $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attachmentRepository = Mockery::mock(AttachmentRepository::class);
        $this->fileSystem           = Mockery::mock(FileSystem::class);
        $this->database             = Mockery::mock(Database::class);

        // Default: transaction executes the closure and returns its value
        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new AttachmentService(
            $this->attachmentRepository,
            $this->fileSystem,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── upload ────────────────────────────────────────────────────────────────

    public function test_upload_stores_file_and_creates_attachment_record(): void
    {
        $file       = $this->makeValidFile();
        $attachment = $this->makeAttachment();

        $this->stubFileSystemForStore();

        $this->attachmentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'member_id'         => 42,
                'site_id'           => 1,
                'attachmentable_id' => 7,
                'uploaded_by'       => 99,
            ]))
            ->andReturn($attachment);

        $result = $this->service->upload(
            $file,
            memberId:         42,
            siteId:           1,
            uploadedByUserId: 99,
            entityType:       AttachmentableType::MANUAL_PAYMENT,
            entityId:         7,
        );

        $this->assertSame($attachment, $result);
    }

    public function test_upload_wraps_writes_in_a_transaction(): void
    {
        $file = $this->makeValidFile();
        $this->stubFileSystemForStore();
        $this->attachmentRepository->shouldReceive('create')->andReturn($this->makeAttachment());

        $this->service->upload($file, 42, 1, 99, AttachmentableType::MANUAL_PAYMENT, 7);

        $this->database->shouldHaveReceived('transaction')->once();

        $this->assertTrue(true);
    }

    public function test_upload_throws_when_file_is_invalid(): void
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(false);
        $file->shouldReceive('getErrorMessage')->andReturn('Upload failed');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Upload failed');

        $this->service->upload($file, 1, 1, 1, AttachmentableType::MANUAL_PAYMENT, 1);
    }

    public function test_upload_throws_for_disallowed_mime_type(): void
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getMimeType')->andReturn('text/plain');
        $file->shouldReceive('getSize')->andReturn(100);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid file type');

        $this->service->upload($file, 1, 1, 1, AttachmentableType::MANUAL_PAYMENT, 1);
    }

    public function test_upload_throws_when_file_exceeds_size_limit(): void
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
        $file->shouldReceive('getSize')->andReturn(6 * 1024 * 1024); // 6 MB

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('5 MB limit');

        $this->service->upload($file, 1, 1, 1, AttachmentableType::MANUAL_PAYMENT, 1);
    }

    public function test_upload_throws_when_directory_creation_fails(): void
    {
        $file = $this->makeValidFile();

        $this->fileSystem->shouldReceive('isDirectory')->andReturn(false);
        $this->fileSystem->shouldReceive('makeDirectory')->andReturn(false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to create upload directory');

        $this->service->upload($file, 1, 1, 1, AttachmentableType::MANUAL_PAYMENT, 1);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function test_delete_removes_file_and_attachment_record(): void
    {
        $attachment            = $this->makeAttachment();
        $attachment->member_id = 42;
        $attachment->stored_path = 'crm/attachments/42/file.jpg';

        $this->attachmentRepository->shouldReceive('find')->with(10)->andReturn($attachment);
        $this->attachmentRepository->shouldReceive('delete')->with(10)->once();

        $this->fileSystem->shouldReceive('fileExists')->andReturn(true);
        $this->fileSystem->shouldReceive('deleteFile')->once();

        $this->service->delete(10, 42);

        $this->addToAssertionCount(1); // reaching here means no exception was thrown
    }

    public function test_delete_wraps_writes_in_a_transaction(): void
    {
        $attachment            = $this->makeAttachment();
        $attachment->member_id = 42;
        $attachment->stored_path = 'crm/attachments/42/file.jpg';

        $this->attachmentRepository->shouldReceive('find')->andReturn($attachment);
        $this->attachmentRepository->shouldReceive('delete');
        $this->fileSystem->shouldReceive('fileExists')->andReturn(false);

        $this->service->delete(10, 42);

        $this->database->shouldHaveReceived('transaction')->once();

        $this->assertTrue(true);
    }

    public function test_delete_throws_when_attachment_not_found(): void
    {
        $this->attachmentRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Attachment not found');

        $this->service->delete(99, 42);
    }

    public function test_delete_throws_when_attachment_belongs_to_different_member(): void
    {
        $attachment            = $this->makeAttachment();
        $attachment->member_id = 999; // different member

        $this->attachmentRepository->shouldReceive('find')->andReturn($attachment);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not belong to this member');

        $this->service->delete(10, 42);
    }

    public function test_delete_skips_file_removal_when_file_does_not_exist_on_disk(): void
    {
        $attachment              = $this->makeAttachment();
        $attachment->member_id   = 42;
        $attachment->stored_path = 'crm/attachments/42/file.jpg';

        $this->attachmentRepository->shouldReceive('find')->andReturn($attachment);
        $this->attachmentRepository->shouldReceive('delete')->once();

        $this->fileSystem->shouldReceive('fileExists')->andReturn(false);
        $this->fileSystem->shouldNotReceive('deleteFile');

        $this->service->delete(10, 42);

        $this->addToAssertionCount(1);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeValidFile(): UploadedFile&MockInterface
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);
        $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
        $file->shouldReceive('getSize')->andReturn(1024 * 100); // 100 KB
        $file->shouldReceive('getClientOriginalName')->andReturn('photo.jpg');
        $file->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
        $file->shouldReceive('moveTo')->andReturn(true);

        return $file;
    }

    private function makeAttachment(): Attachment
    {
        $attachment        = Mockery::mock(Attachment::class)->makePartial();
        $attachment->id    = 10;
        $attachment->member_id = 42;

        return $attachment;
    }

    private function stubFileSystemForStore(): void
    {
        $this->fileSystem->shouldReceive('isDirectory')->andReturn(true);
    }
}