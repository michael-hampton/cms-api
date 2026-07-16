<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Models\PrintBatch;
use App\Services\Subscriptions\Printing\PrintBatchDownloadService;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PrintBatchDownloadServiceTest extends TestCase
{
    private PrintExportTransport|MockInterface $transport;
    private PrintBatchDownloadService $service;

    public function test_file_exists_delegates_to_transport(): void
    {
        $batch = $this->makeBatch(filePath: 'batch_1_v1_20260101_000000.csv');

        $this->transport
            ->shouldReceive('exists')
            ->once()
            ->with('batch_1_v1_20260101_000000.csv')
            ->andReturn(true);

        $this->assertTrue($this->service->fileExists($batch));
    }

    public function test_file_exists_is_false_when_batch_has_no_file_path(): void
    {
        $batch = $this->makeBatch(filePath: null);

        $this->transport->shouldNotReceive('exists');

        $this->assertFalse($this->service->fileExists($batch));
    }

    public function test_file_size_delegates_to_transport(): void
    {
        $batch = $this->makeBatch(filePath: 'batch_1_v1_20260101_000000.csv');

        $this->transport
            ->shouldReceive('size')
            ->once()
            ->with('batch_1_v1_20260101_000000.csv')
            ->andReturn(1024);

        $this->assertSame(1024, $this->service->fileSize($batch));
    }

    public function test_file_size_is_null_when_batch_has_no_file_path(): void
    {
        $batch = $this->makeBatch(filePath: null);

        $this->transport->shouldNotReceive('size');

        $this->assertNull($this->service->fileSize($batch));
    }

    public function test_download_returns_exported_file_with_filename_and_mime_type(): void
    {
        $batch = $this->makeBatch(filePath: 'batch_1_v1_20260101_000000.csv', format: 'csv');

        $this->transport
            ->shouldReceive('download')
            ->once()
            ->with('batch_1_v1_20260101_000000.csv')
            ->andReturn('id,name');

        $file = $this->service->download($batch);

        $this->assertSame('batch_1_v1_20260101_000000.csv', $file->filename);
        $this->assertSame('id,name', $file->contents);
        $this->assertSame('text/csv', $file->mimeType);
    }

    public function test_download_throws_when_batch_has_no_file_path(): void
    {
        $batch = $this->makeBatch(filePath: null);

        $this->transport->shouldNotReceive('download');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/has no exported file yet/');

        $this->service->download($batch);
    }

    private function makeBatch(?string $filePath, string $format = 'csv'): PrintBatch|MockInterface
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = 1;
        $batch->file_path = $filePath;
        $batch->format = $format;

        return $batch;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = Mockery::mock(PrintExportTransport::class);
        $this->service = new PrintBatchDownloadService($this->transport);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
