<?php

namespace App\Tests\Unit\Services\PublicContent\Diagnostics;

use App\Services\PublicContent\Diagnostics\JsonLinesFileReader;
use App\Services\PublicContent\Diagnostics\PublicContentDiagnosticsDashboardViewModel;
use App\Services\PublicContent\Diagnostics\PublicContentDiagnosticsReportWriter;
use App\Services\PublicContent\Parity\PublicContentParityReportWriter;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

final class PublicContentDiagnosticsDashboardViewModelTest extends MockeryTestCase
{
    public function test_it_only_includes_records_for_the_requested_site(): void
    {
        $reader = Mockery::mock(JsonLinesFileReader::class);
        $skipWriter = Mockery::mock(PublicContentDiagnosticsReportWriter::class);
        $parityWriter = Mockery::mock(PublicContentParityReportWriter::class);

        $skipWriter->shouldReceive('path')->andReturn('/tmp/skips.jsonl');
        $parityWriter->shouldReceive('path')->andReturn('/tmp/parity.jsonl');

        $reader->shouldReceive('tail')->with('/tmp/skips.jsonl', 200)->andReturn([
            ['site_id' => 1, 'widget' => 'trending', 'reason' => 'empty_html'],
            ['site_id' => 2, 'widget' => 'deals', 'reason' => 'empty_html'],
        ]);

        $reader->shouldReceive('tail')->with('/tmp/parity.jsonl', 200)->andReturn([
            ['site_id' => 1, 'status' => 'mismatched'],
            ['site_id' => 2, 'status' => 'mismatched'],
        ]);

        $viewModel = new PublicContentDiagnosticsDashboardViewModel($reader, $skipWriter, $parityWriter);
        $result = $viewModel->build(1, 'my-site');

        self::assertCount(1, $result['skips']);
        self::assertSame('trending', $result['skips'][0]['widget']);
        self::assertCount(1, $result['parityMismatches']);
        self::assertSame('my-site', $result['siteSlug']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}