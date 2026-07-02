<?php

namespace App\Tests\Unit\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentContext;
use App\Enums\PublicContent\WidgetSkipReason;
use App\Framework\Support\Logger;
use App\Models\Page;
use App\Services\PublicContent\Diagnostics\PublicContentDiagnosticsReportWriter;
use App\Services\PublicContent\Diagnostics\PublicContentWidgetDiagnostics;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use RuntimeException;

final class PublicContentWidgetDiagnosticsTest extends MockeryTestCase
{
    public function test_it_persists_a_skipped_widget_via_the_report_writer(): void
    {
        $reportWriter = Mockery::mock(PublicContentDiagnosticsReportWriter::class);
        $logger = Mockery::mock(Logger::class);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 42;

        // FIX: Instantiate the DTO directly instead of mocking it
        $context = new PublicContentContext(
            page: $page,
            siteId: 7,
            siteSlug: 'test-site'
        );

        $reportWriter->shouldReceive('append')
            ->once()
            ->with(Mockery::on(
                static fn (array $record): bool =>
                    $record['widget'] === 'trending'
                    && $record['reason'] === WidgetSkipReason::SupportsFailed->value
                    && $record['page_id'] === 42
                    && $record['site_id'] === 7
            ));

        $logger->shouldNotReceive('warning');

        $diagnostics = new PublicContentWidgetDiagnostics($reportWriter, $logger);
        $diagnostics->recordSkipped('trending', WidgetSkipReason::SupportsFailed, $context);

        self::assertCount(1, $diagnostics->skipped());
        self::assertSame('trending', $diagnostics->skipped()[0]['widget']);
    }

    public function test_it_logs_and_does_not_throw_when_the_writer_fails(): void
    {
        $reportWriter = Mockery::mock(PublicContentDiagnosticsReportWriter::class);
        $logger = Mockery::mock(Logger::class);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;

        // FIX: Instantiate the DTO directly instead of mocking it
        $context = new PublicContentContext(
            page: $page,
            siteId: 1,
            siteSlug: 'test-site'
        );

        $reportWriter->shouldReceive('append')->once()->andThrow(new RuntimeException('disk full'));
        $reportWriter->shouldReceive('path')->andReturn('/tmp/whatever.jsonl');
        $logger->shouldReceive('warning')->once();

        $diagnostics = new PublicContentWidgetDiagnostics($reportWriter, $logger);
        $diagnostics->recordSkipped('deals', WidgetSkipReason::EmptyHtml, $context);

        // non-critical flow: swallow + log, never throw, in-memory record still captured
        self::assertCount(1, $diagnostics->skipped());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}