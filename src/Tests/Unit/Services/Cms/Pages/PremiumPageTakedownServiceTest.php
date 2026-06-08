<?php

namespace App\Tests\Unit\Services\Cms\Pages;

use App\Enums\OpenCollab\AccrualStatus;
use App\Events\OpenCollab\PremiumMonetisationApprovedEvent;
use App\Events\OpenCollab\PremiumMonetisationDisabledEvent;
use App\Framework\Events\EventDispatcher;
use App\Models\EarningsLedger;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageMetadataRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Services\Cms\Pages\PageHistoryService;
use App\Services\Cms\Pages\PremiumPageTakedownService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PremiumPageTakedownServiceTest extends TestCase
{
    private PageRepository $pageRepository;
    private PageMetadataRepository $metadataRepository;
    private EarningsLedgerRepository $ledgerRepository;
    private PageHistoryService $historyService;
    private PremiumPageTakedownService $service;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->metadataRepository = Mockery::mock(PageMetadataRepository::class);
        $this->ledgerRepository = Mockery::mock(EarningsLedgerRepository::class);
        $this->historyService = Mockery::mock(PageHistoryService::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);

        $this->service = new PremiumPageTakedownService(
            $this->pageRepository,
            $this->metadataRepository,
            $this->ledgerRepository,
            $this->historyService,
            $this->eventDispatcher
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_disable_monetisation_reverses_active_entries_and_ignores_terminal_entries(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'is_paid' => true,
        ]);

        $estimated = $this->mockLedger([
            'id' => 1,
            'article_id' => 123,
            'accrual_status' => AccrualStatus::Estimated->value,
        ]);

        $confirmed = $this->mockLedger([
            'id' => 2,
            'article_id' => 123,
            'accrual_status' => AccrualStatus::Confirmed->value,
        ]);

        $settled = $this->mockLedger([
            'id' => 3,
            'article_id' => 123,
            'accrual_status' => AccrualStatus::Settled->value,
        ]);

        $withdrawn = $this->mockLedger([
            'id' => 4,
            'article_id' => 123,
            'accrual_status' => AccrualStatus::Withdrawn->value,
        ]);

        $reversed = $this->mockLedger([
            'id' => 5,
            'article_id' => 123,
            'accrual_status' => AccrualStatus::Reversed->value,
        ]);

        $updated = $this->mockPage([
            'id' => 123,
            'is_paid' => false,
            'monetisation_disabled_by' => 99,
        ]);

        $this->pageRepository
            ->shouldReceive('update')
            ->once()
            ->with(123, Mockery::on(function (array $data): bool {
                return $data['is_paid'] === false
                    && $data['monetisation_disabled_by'] === 99
                    && $data['monetisation_disabled_reason'] === 'emergency_takedown'
                    && !empty($data['monetisation_disabled_at']);
            }));

        $this->metadataRepository
            ->shouldReceive('createOrUpdate')
            ->once()
            ->with(123, ['visibility' => 'public']);

        $this->ledgerRepository
            ->shouldReceive('forArticle')
            ->once()
            ->with(123)
            ->andReturn(collect([$estimated, $confirmed, $settled, $withdrawn, $reversed]));

        $this->ledgerRepository
            ->shouldReceive('reverse')
            ->once()
            ->with(1, 'premium_takedown:emergency_takedown', 99);

        $this->ledgerRepository
            ->shouldReceive('reverse')
            ->once()
            ->with(2, 'premium_takedown:emergency_takedown', 99);

        $this->ledgerRepository
            ->shouldReceive('reverse')
            ->once()
            ->with(3, 'premium_takedown:emergency_takedown', 99);

        $this->historyService
            ->shouldReceive('logPageAction')
            ->once()
            ->withArgs(function (
                int $pageId,
                string $action,
                ?string $description,
                ?array $changes,
                bool $includeSnapshot
            ): bool {
                return $pageId === 123
                    && $action === 'premium_disabled'
                    && $description === 'Premium monetisation disabled'
                    && $changes['reason'] === 'emergency_takedown'
                    && $changes['reversal_summary']['reversed'] === 3
                    && $changes['reversal_summary']['withdrawn_flagged'] === 1
                    && $changes['reversal_summary']['ignored'] === 1
                    && $includeSnapshot === true;
            });

        $this->pageRepository
            ->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn($updated);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($event): bool {
                return $event instanceof PremiumMonetisationDisabledEvent
                    && (int) $event->adminId === 99;
            });

        $result = $this->service->disableMonetisation($page, 99, 'emergency_takedown');

        $this->assertSame($updated, $result);
    }

    public function test_disable_monetisation_with_no_earnings_still_disables_page(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'is_paid' => true,
        ]);

        $updated = $this->mockPage([
            'id' => 123,
            'is_paid' => false,
        ]);

        $this->pageRepository
            ->shouldReceive('update')
            ->once();

        $this->metadataRepository
            ->shouldReceive('createOrUpdate')
            ->once()
            ->with(123, ['visibility' => 'public']);

        $this->ledgerRepository
            ->shouldReceive('forArticle')
            ->once()
            ->with(123)
            ->andReturn(collect([]));

        $this->historyService
            ->shouldReceive('logPageAction')
            ->once()
            ->withArgs(function (
                int $pageId,
                string $action,
                ?string $description,
                ?array $changes,
                bool $includeSnapshot
            ): bool {
                return $pageId === 123
                    && $action === 'premium_disabled'
                    && $changes['reversal_summary']['reversed'] === 0
                    && $changes['reversal_summary']['withdrawn_flagged'] === 0
                    && $changes['reversal_summary']['ignored'] === 0;
            });

        $this->pageRepository
            ->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn($updated);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($event): bool {
                return $event instanceof PremiumMonetisationDisabledEvent
                    && (int) $event->adminId === 99;
            });

        $result = $this->service->disableMonetisation($page, 99, 'policy_breach');

        $this->assertSame($updated, $result);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function mockPage(array $attributes): Page&MockInterface
    {
        /** @var Page&MockInterface $page */
        $page = Mockery::mock(Page::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $page->{$key} = $value;
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function mockLedger(array $attributes): EarningsLedger&MockInterface
    {
        /** @var EarningsLedger&MockInterface $ledger */
        $ledger = Mockery::mock(EarningsLedger::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $ledger->{$key} = $value;
        }

        return $ledger;
    }
}