<?php

namespace App\Tests\Unit\Services\PublicContent\Diagnostics;

use App\Services\PublicContent\Diagnostics\JsonLinesFileReader;
use App\Services\PublicContent\Diagnostics\PublicContentDiagnosticsDashboardViewModel;
use App\Services\PublicContent\Diagnostics\PublicContentDiagnosticsReportWriter;
use App\Services\PublicContent\Parity\PublicContentParityReportWriter;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentDiagnosticsDashboardViewModelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_filters_records_to_the_requested_site_and_counts_by_widget_and_reason(): void
    {
        $skipWriter = Mockery::mock(PublicContentDiagnosticsReportWriter::class);
        $skipWriter->shouldReceive('path')->andReturn('/tmp/skips.jsonl');

        $parityWriter = Mockery::mock(PublicContentParityReportWriter::class);
        $parityWriter->shouldReceive('path')->andReturn('/tmp/parity.jsonl');

        $reader = Mockery::mock(JsonLinesFileReader::class);
        $reader->shouldReceive('tail')->with('/tmp/skips.jsonl', 200)->andReturn([
            ['site_id' => 1, 'widget' => 'authors', 'reason' => 'empty_html'],
            ['site_id' => 1, 'widget' => 'authors', 'reason' => 'empty_html'],
            ['site_id' => 1, 'widget' => 'recirculation', 'reason' => 'supports_failed'],
            ['site_id' => 2, 'widget' => 'authors', 'reason' => 'empty_html'],
        ]);
        $reader->shouldReceive('tail')->with('/tmp/parity.jsonl', 200)->andReturn([
            ['site_id' => 1, 'status' => 'mismatched'],
            ['site_id' => 1, 'status' => 'failed'],
            ['site_id' => 1, 'status' => 'matched'],
            ['site_id' => 2, 'status' => 'mismatched'],
        ]);

        $viewModel = new PublicContentDiagnosticsDashboardViewModel($reader, $skipWriter, $parityWriter);

        $result = $viewModel->build(1, 'estate');

        self::assertSame('estate', $result['siteSlug']);
        self::assertCount(3, $result['skips']);
        self::assertSame(['authors' => 2, 'recirculation' => 1], $result['skipCountsByWidget']);
        self::assertSame(['empty_html' => 2, 'supports_failed' => 1], $result['skipCountsByReason']);
        self::assertCount(3, $result['parityRecords']);
        self::assertCount(1, $result['parityMismatches']);
        self::assertCount(1, $result['parityFailures']);
    }

    public function test_it_returns_empty_aggregates_when_no_records_match_the_site(): void
    {
        $skipWriter = Mockery::mock(PublicContentDiagnosticsReportWriter::class);
        $skipWriter->shouldReceive('path')->andReturn('/tmp/skips.jsonl');

        $parityWriter = Mockery::mock(PublicContentParityReportWriter::class);
        $parityWriter->shouldReceive('path')->andReturn('/tmp/parity.jsonl');

        $reader = Mockery::mock(JsonLinesFileReader::class);
        $reader->shouldReceive('tail')->with('/tmp/skips.jsonl', 200)->andReturn([
            ['site_id' => 2, 'widget' => 'authors', 'reason' => 'empty_html'],
        ]);
        $reader->shouldReceive('tail')->with('/tmp/parity.jsonl', 200)->andReturn([]);

        $viewModel = new PublicContentDiagnosticsDashboardViewModel($reader, $skipWriter, $parityWriter);

        $result = $viewModel->build(1, 'estate');

        self::assertSame([], $result['skips']);
        self::assertSame([], $result['skipCountsByWidget']);
        self::assertSame([], $result['parityRecords']);
        self::assertSame([], $result['parityMismatches']);
        self::assertSame([], $result['parityFailures']);
    }
}