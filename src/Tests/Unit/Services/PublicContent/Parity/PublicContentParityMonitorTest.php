<?php

namespace App\Tests\Unit\Services\PublicContent\Parity;

use App\DTO\PublicContent\ContentRegion;
use App\DTO\PublicContent\PublicContentDocument;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\Cms\Pages\PageRenderService;
use App\Services\PublicContent\Parity\PublicContentParityKillPath;
use App\Services\PublicContent\Parity\PublicContentParityMonitor;
use App\Services\PublicContent\Parity\PublicContentParityReportWriter;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

/**
 * PublicContentDocument resolves its design tokens via the app container in
 * its own constructor, so building one requires a bootstrapped app - hence
 * FunctionalTestCase rather than a plain TestCase, matching the convention
 * used by PublicContentResourceTest for the same reason. The monitor's own
 * dependencies (repository, renderer, report writer, logger) are all
 * non-final and are mocked as usual.
 */
final class PublicContentParityMonitorTest extends FunctionalTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        putenv('PUBLIC_CONTENT_PARITY_ENABLED');
        putenv('PUBLIC_CONTENT_PARITY_SAMPLE_PERCENT');
        putenv('PUBLIC_CONTENT_PARITY_LOG_MATCHES');
        parent::tearDown();
    }

    public function test_it_does_nothing_when_parity_checking_is_disabled(): void
    {
        putenv('PUBLIC_CONTENT_PARITY_ENABLED=0');

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findCompletePublishedBySlug')->never();

        $reportWriter = Mockery::mock(PublicContentParityReportWriter::class);
        $reportWriter->shouldReceive('append')->never();

        $monitor = new PublicContentParityMonitor(
            $pages,
            Mockery::mock(PageRenderService::class),
            $reportWriter,
            Mockery::mock(Logger::class),
        );

        $monitor->compareDocument($this->document(), null);

        $this->assertTrue(true);
    }

    public function test_it_records_an_unresolved_status_when_the_legacy_page_cannot_be_found(): void
    {
        putenv('PUBLIC_CONTENT_PARITY_ENABLED=1');
        putenv('PUBLIC_CONTENT_PARITY_SAMPLE_PERCENT=100');

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findCompletePublishedBySlug')->once()->andReturn(null);

        $reportWriter = $this->reportWriter();
        $reportWriter->shouldReceive('append')->once()->with(Mockery::on(
            static fn(array $record): bool => $record['status'] === 'unresolved',
        ));

        $monitor = new PublicContentParityMonitor(
            $pages,
            Mockery::mock(PageRenderService::class),
            $reportWriter,
            Mockery::mock(Logger::class),
        );

        $monitor->compareDocument($this->document(), null);

        $this->assertTrue(true);
    }

    public function test_it_records_a_matched_status_and_reports_a_match_to_the_kill_path_when_html_is_identical(): void
    {
        putenv('PUBLIC_CONTENT_PARITY_ENABLED=1');
        putenv('PUBLIC_CONTENT_PARITY_SAMPLE_PERCENT=100');

        $page = $this->parityPage(
            title: 'Example page',
            pageType: 'article',
            summary: 'Example summary',
        );

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findCompletePublishedBySlug')->once()->andReturn($page);

        $renderer = Mockery::mock(PageRenderService::class);
        $renderer->shouldReceive('renderPage')->once()->andReturn([
            'main' => '<h2>Hello</h2>',
            'sidebar' => '',
        ]);

        $reportWriter = $this->reportWriter();
        $reportWriter->shouldReceive('append')->once()->with(Mockery::on(
            static fn(array $record): bool => $record['status'] === 'matched',
        ));

        $killPath = Mockery::mock(PublicContentParityKillPath::class);
        $killPath->shouldReceive('recordMatch')->once();
        $killPath->shouldReceive('recordMismatch')->never();

        $monitor = new PublicContentParityMonitor(
            $pages,
            $renderer,
            $reportWriter,
            Mockery::mock(Logger::class),
            $killPath,
        );

        $monitor->compareDocument($this->document(), null);

        $this->assertTrue(true);
    }

    public function test_it_records_a_mismatched_status_and_reports_a_mismatch_to_the_kill_path_when_titles_differ(): void
    {
        putenv('PUBLIC_CONTENT_PARITY_ENABLED=1');
        putenv('PUBLIC_CONTENT_PARITY_SAMPLE_PERCENT=100');

        $page = $this->parityPage(
            title: 'A completely different title',
            pageType: 'article',
            summary: 'Example summary',
        );

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findCompletePublishedBySlug')->once()->andReturn($page);

        $renderer = Mockery::mock(PageRenderService::class);
        $renderer->shouldReceive('renderPage')->once()->andReturn([
            'main' => '<h2>Hello</h2>',
            'sidebar' => '',
        ]);

        $reportWriter = $this->reportWriter();
        $reportWriter->shouldReceive('append')->once()->with(Mockery::on(
            static fn(array $record): bool => $record['status'] === 'mismatched'
                && array_key_exists('title', $record['differences']),
        ));

        $killPath = Mockery::mock(PublicContentParityKillPath::class);
        $killPath->shouldReceive('recordMismatch')->once()->with($this->siteId);

        $monitor = new PublicContentParityMonitor(
            $pages,
            $renderer,
            $reportWriter,
            Mockery::mock(Logger::class),
            $killPath,
        );

        $monitor->compareDocument($this->document(), null);

        $this->assertTrue(true);
    }

    public function test_it_records_a_failed_status_and_a_mismatch_when_the_legacy_renderer_throws(): void
    {
        putenv('PUBLIC_CONTENT_PARITY_ENABLED=1');
        putenv('PUBLIC_CONTENT_PARITY_SAMPLE_PERCENT=100');

        $page = $this->parityPage(title: 'Example page');

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findCompletePublishedBySlug')->once()->andReturn($page);

        $renderer = Mockery::mock(PageRenderService::class);
        $renderer->shouldReceive('renderPage')->once()->andThrow(new RuntimeException('render failed'));

        $reportWriter = $this->reportWriter();
        $reportWriter->shouldReceive('append')->once()->with(Mockery::on(
            static fn(array $record): bool => $record['status'] === 'failed'
                && $record['error']['type'] === RuntimeException::class,
        ));

        $killPath = Mockery::mock(PublicContentParityKillPath::class);
        $killPath->shouldReceive('recordMismatch')->once();

        $monitor = new PublicContentParityMonitor(
            $pages,
            $renderer,
            $reportWriter,
            Mockery::mock(Logger::class),
            $killPath,
        );

        $monitor->compareDocument($this->document(), null);

        $this->assertTrue(true);
    }

    public function test_a_report_writer_failure_is_logged_rather_than_thrown(): void
    {
        putenv('PUBLIC_CONTENT_PARITY_ENABLED=1');
        putenv('PUBLIC_CONTENT_PARITY_SAMPLE_PERCENT=100');

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findCompletePublishedBySlug')->once()->andReturn(null);

        $reportWriter = $this->reportWriter();
        $reportWriter->shouldReceive('append')->once()->andThrow(new RuntimeException('disk full'));

        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('warning')->once()->with(
            Mockery::type('string'),
            Mockery::on(static fn(array $context): bool => $context['exception'] === RuntimeException::class),
        );

        $monitor = new PublicContentParityMonitor(
            $pages,
            Mockery::mock(PageRenderService::class),
            $reportWriter,
            $logger,
        );

        // Must not throw - a diagnostics failure must never break page rendering.
        $monitor->compareDocument($this->document(), null);

        $this->assertTrue(true);
    }

    private function reportWriter(): MockInterface
    {
        $reportWriter = Mockery::mock(PublicContentParityReportWriter::class);
        $reportWriter->shouldReceive('path')->andReturn('/tmp/parity.jsonl')->byDefault();

        return $reportWriter;
    }

    private function parityPage(
        string $title,
        string $pageType = 'article',
        ?string $summary = null,
    ): Page {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->title = $title;
        $page->page_type = $pageType;
        $page->meta_description = $summary;
        // Avoid lazy relation resolution on a partial mock (needs a live Database).
        $page->setRelation('categories', new Collection());
        $page->setRelation('tags', new Collection());
        $page->setRelation('authors', new Collection());

        return $page;
    }

    private function document(): PublicContentDocument
    {
        return new PublicContentDocument(
            id: 10,
            siteId: $this->siteId,
            slug: 'example-page',
            type: 'article',
            title: 'Example page',
            summary: 'Example summary',
            seo: ['meta_title' => 'Example page'],
            taxonomy: ['categories' => [], 'tags' => []],
            regions: [
                'main' => new ContentRegion('main', [], '<h2>Hello</h2>'),
                'sidebar' => new ContentRegion('sidebar', [], ''),
            ],
        );
    }
}
