<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Services\Subscriptions\Printing\Transport\LocalPrintExportTransport;
use App\Tests\Unit\UnitTestCase;

class LocalPrintExportTransportTest extends UnitTestCase
{
    private string $tempDir;
    private LocalPrintExportTransport $transport;

    public function test_writes_file_to_export_directory(): void
    {
        $this->transport->upload('batch_1_20260304_120000.csv', 'header,row');

        $expectedPath = $this->tempDir . '/batch_1_20260304_120000.csv';
        $this->assertFileExists($expectedPath);
        $this->assertSame('header,row', file_get_contents($expectedPath));
    }

    public function test_creates_directory_if_not_exists(): void
    {
        $nestedDir = $this->tempDir . '/nested/deep';
        $transport = new LocalPrintExportTransport($nestedDir);

        $transport->upload('batch_2.csv', 'content');

        $this->assertFileExists($nestedDir . '/batch_2.csv');

        // Cleanup nested dirs
        unlink($nestedDir . '/batch_2.csv');
        rmdir($nestedDir);
        rmdir(dirname($nestedDir));
    }

    public function test_overwrites_existing_file(): void
    {
        $this->transport->upload('batch_3.csv', 'original');
        $this->transport->upload('batch_3.csv', 'updated');

        $this->assertSame('updated', file_get_contents($this->tempDir . '/batch_3.csv'));
    }

    public function test_trims_leading_slash_from_path(): void
    {
        $this->transport->upload('/batch_4.csv', 'data');

        $this->assertFileExists($this->tempDir . '/batch_4.csv');
    }

    public function test_throws_when_directory_cannot_be_created(): void
    {
        // Regression test: upload() previously logged a warning and
        // returned silently on a write failure instead of throwing.
        // PrintBatchExportService relies on upload() throwing to detect
        // failure — a silent return meant a failed local write (used for
        // preview/ad-hoc exports) was recorded as markPreviewGenerated(),
        // a successful outcome for a file that was never actually written.
        //
        // A file existing at the target directory path (rather than a
        // directory) makes mkdir() fail deterministically.
        $blockingFilePath = sys_get_temp_dir() . '/print_export_blocker_' . uniqid('', true);
        file_put_contents($blockingFilePath, 'not a directory');

        $transport = new LocalPrintExportTransport($blockingFilePath . '/subdir');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create print export directory');

        try {
            $transport->upload('batch.csv', 'content');
        } finally {
            unlink($blockingFilePath);
        }
    }

    protected function setUp(): void
    {

        $this->tempDir = sys_get_temp_dir() . '/print_export_test_' . uniqid('', true);
        $this->transport = new LocalPrintExportTransport($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory after each test.
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }
}