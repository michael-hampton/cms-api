<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Printing\Label;

use App\Models\LabelRun;
use App\Services\Subscriptions\Printing\Label\LabelRunDownloadService;
use App\Services\Subscriptions\Printing\Transport\LocalLabelExportTransport;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class LabelRunDownloadServiceTest extends TestCase
{
    private LocalLabelExportTransport|MockInterface $transport;
    private LabelRunDownloadService $service;

    public function test_file_exists_delegates_to_transport(): void
    {
        $labelRun = $this->makeLabelRun(filePath: 'label_1_v1_20260101_000000.pdf');

        $this->transport
            ->shouldReceive('exists')
            ->once()
            ->with('label_1_v1_20260101_000000.pdf')
            ->andReturn(true);

        $this->assertTrue($this->service->fileExists($labelRun));
    }

    public function test_file_exists_is_false_when_run_has_no_file_path(): void
    {
        $labelRun = $this->makeLabelRun(filePath: null);

        $this->transport->shouldNotReceive('exists');

        $this->assertFalse($this->service->fileExists($labelRun));
    }

    public function test_file_size_delegates_to_transport(): void
    {
        $labelRun = $this->makeLabelRun(filePath: 'label_1_v1_20260101_000000.pdf');

        $this->transport
            ->shouldReceive('size')
            ->once()
            ->with('label_1_v1_20260101_000000.pdf')
            ->andReturn(2048);

        $this->assertSame(2048, $this->service->fileSize($labelRun));
    }

    public function test_download_returns_exported_file_with_filename_and_mime_type(): void
    {
        $labelRun = $this->makeLabelRun(filePath: 'label_1_v1_20260101_000000.pdf', format: 'pdf');

        $this->transport
            ->shouldReceive('download')
            ->once()
            ->with('label_1_v1_20260101_000000.pdf')
            ->andReturn('%PDF-1.4 ...');

        $file = $this->service->download($labelRun);

        $this->assertSame('label_1_v1_20260101_000000.pdf', $file->filename);
        $this->assertSame('%PDF-1.4 ...', $file->contents);
        $this->assertSame('application/pdf', $file->mimeType);
    }

    public function test_download_throws_when_run_has_no_file_path(): void
    {
        $labelRun = $this->makeLabelRun(filePath: null);

        $this->transport->shouldNotReceive('download');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/has no generated file yet/');

        $this->service->download($labelRun);
    }

    private function makeLabelRun(?string $filePath, string $format = 'pdf'): LabelRun|MockInterface
    {
        $labelRun = Mockery::mock(LabelRun::class)->makePartial();
        $labelRun->id = 1;
        $labelRun->file_path = $filePath;
        $labelRun->format = $format;

        return $labelRun;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = Mockery::mock(LocalLabelExportTransport::class);
        $this->service = new LabelRunDownloadService($this->transport);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
