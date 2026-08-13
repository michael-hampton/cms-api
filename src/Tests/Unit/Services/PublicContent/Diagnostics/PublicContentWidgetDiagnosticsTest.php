<?php

namespace App\Tests\Unit\Services\PublicContent\Diagnostics;

use App\DTO\PublicContent\PublicContentContext;
use App\Enums\PublicContent\WidgetSkipReason;
use App\Framework\Support\Logger;
use App\Models\Page;
use App\Services\PublicContent\Diagnostics\PublicContentDiagnosticsReportWriter;
use App\Services\PublicContent\Diagnostics\PublicContentWidgetDiagnostics;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicContentWidgetDiagnosticsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_skipped_is_empty_before_anything_is_recorded(): void
    {
        $diagnostics = new PublicContentWidgetDiagnostics(
            Mockery::mock(PublicContentDiagnosticsReportWriter::class)->shouldIgnoreMissing(),
            Mockery::mock(Logger::class)->shouldIgnoreMissing(),
        );

        self::assertSame([], $diagnostics->skipped());
    }

    public function test_record_skipped_appends_to_the_in_memory_list_and_persists(): void
    {
        $writer = Mockery::mock(PublicContentDiagnosticsReportWriter::class);
        $writer->shouldReceive('path')->andReturn('test');
        $writer->shouldReceive('append')->once()->with(Mockery::on(function (array $record): bool {
            return $record['widget'] === 'authors'
                && $record['reason'] === WidgetSkipReason::EmptyHtml->value
                && $record['page_id'] === 42
                && $record['site_id'] === 1;
        }));

        $diagnostics = new PublicContentWidgetDiagnostics($writer, Mockery::mock(Logger::class));

        $diagnostics->recordSkipped('authors', WidgetSkipReason::EmptyHtml, $this->context());

        self::assertCount(1, $diagnostics->skipped());
        self::assertSame('authors', $diagnostics->skipped()[0]['widget']);
        self::assertSame('empty_html', $diagnostics->skipped()[0]['reason']);
    }

    public function test_reset_clears_the_in_memory_list(): void
    {
        $writer = Mockery::mock(PublicContentDiagnosticsReportWriter::class);
        $writer->shouldReceive('append');

        $diagnostics = new PublicContentWidgetDiagnostics($writer, Mockery::mock(Logger::class));
        $diagnostics->recordSkipped('authors', WidgetSkipReason::EmptyHtml, $this->context());

        $diagnostics->reset();

        self::assertSame([], $diagnostics->skipped());
    }

    public function test_a_persistence_failure_is_logged_rather_than_thrown(): void
    {
        $writer = Mockery::mock(PublicContentDiagnosticsReportWriter::class);
        $writer->shouldReceive('append')->once()->andThrow(new RuntimeException('disk full'));
        $writer->shouldReceive('path')->andReturn('/tmp/skips.jsonl');

        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('warning')->once()->with(
            Mockery::type('string'),
            Mockery::on(static fn(array $context): bool => $context['exception'] === RuntimeException::class),
        );

        $diagnostics = new PublicContentWidgetDiagnostics($writer, $logger);

        // Must not throw - a diagnostics failure must never break page rendering.
        $diagnostics->recordSkipped('authors', WidgetSkipReason::SupportsFailed, $this->context());

        self::assertCount(1, $diagnostics->skipped());
    }

    private function context(): PublicContentContext
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 42;

        return new PublicContentContext(page: $page, siteId: 1, siteSlug: 'estate');
    }
}