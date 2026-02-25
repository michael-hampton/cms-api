<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Database\Database;
use App\Models\NewsletterSendRecipient;
use App\Models\NewsletterSnapshot;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;
use App\Services\Newsletter\NewsletterViewInBrowserService;
use App\Services\Newsletter\NewsletterViewTokenService;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterViewInBrowserServiceTest extends TestCase
{
    private NewsletterSendRecipientRepository $newsletterSendRecipientRepository;

    public function setUp(): void
    {
        $this->newsletterSendRecipientRepository = Mockery::mock(NewsletterSendRecipientRepository::class);
        parent::setUp();
    }

    public function test_it_builds_a_per_recipient_view_url()
    {
        $viewTokenService = Mockery::mock(NewsletterViewTokenService::class);
        $viewTokenService
            ->shouldReceive('buildViewUrl')
            ->once()
            ->with('snapshot-token')
            ->andReturn('https://example.com/newsletter/view/snapshot-token');

        $service = $this->makeService($viewTokenService);

        $url = $service->buildViewUrl('snapshot-token', 'recipient-token');

        $this->assertEquals(
            'https://example.com/newsletter/view/snapshot-token?r=recipient-token',
            $url
        );
    }

    private function makeService(
        $viewTokenService = null,
        $snapshotRepository = null,
        $database = null
    ): NewsletterViewInBrowserService
    {
        return new NewsletterViewInBrowserService(
            $viewTokenService ?? Mockery::mock(NewsletterViewTokenService::class),
            $snapshotRepository ?? Mockery::mock(NewsletterSnapshotRepository::class),
            $this->newsletterSendRecipientRepository,
            $database ?? Mockery::mock(Database::class),
        );
    }

    public function test_record_view_does_nothing_if_snapshot_not_found()
    {
        $viewTokenService = Mockery::mock(NewsletterViewTokenService::class);
        $viewTokenService
            ->shouldReceive('resolveSnapshot')
            ->once()
            ->with('snapshot-token')
            ->andReturn(null);

        $snapshotRepository = Mockery::mock(NewsletterSnapshotRepository::class);
        $snapshotRepository->shouldNotReceive('recordViewInBrowserClick');

        $service = $this->makeService($viewTokenService, $snapshotRepository);

        $service->recordView('snapshot-token', 'recipient-token');

        $this->assertTrue(true); // no exception = pass
    }

    public function test_record_view_does_nothing_if_recipient_not_found()
    {
        $snapshot = Mockery::mock(NewsletterSnapshot::class)->makePartial();

        $viewTokenService = Mockery::mock(NewsletterViewTokenService::class);
        $viewTokenService
            ->shouldReceive('resolveSnapshot')
            ->once()
            ->with('snapshot-token')
            ->andReturn($snapshot);

        $snapshotRepository = Mockery::mock(NewsletterSnapshotRepository::class);
        $snapshotRepository->shouldNotReceive('recordViewInBrowserClick');

        $this->newsletterSendRecipientRepository->shouldReceive('findByViewToken')->andReturn(null);

        $service = $this->makeService($viewTokenService, $snapshotRepository);

        $service->recordView('snapshot-token', 'recipient-token');

        $this->assertTrue(true);
    }

    public function test_it_records_view_when_snapshot_and_recipient_exist()
    {
        $snapshot = Mockery::mock(NewsletterSnapshot::class)->makePartial();
        $snapshot->id = 10;
        $recipient = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
        $recipient->id = 99;

        $viewTokenService = Mockery::mock(NewsletterViewTokenService::class);
        $viewTokenService
            ->shouldReceive('resolveSnapshot')
            ->once()
            ->with('snapshot-token')
            ->andReturn($snapshot);

        $snapshotRepository = Mockery::mock(NewsletterSnapshotRepository::class);
        $snapshotRepository
            ->shouldReceive('recordViewInBrowserClick')
            ->once()
            ->with(10, 99);

        $this->newsletterSendRecipientRepository->shouldReceive('findByViewToken')
            ->andReturn($recipient);

        $service = $this->makeService($viewTokenService, $snapshotRepository);

        $service->recordView('snapshot-token', 'recipient-token');
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}