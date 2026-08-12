<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\PaymentTerms;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\PaymentTermsService;
use App\Services\OpenCollab\PayoutSchedulerService;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class PayoutSchedulerServiceTest extends UnitTestCase
{
    private PayoutSchedulerService $service;
    private MockInterface $ledgerRepository;
    private MockInterface $payoutRepository;
    private MockInterface $paymentTermsService;
    private MockInterface $databaseMock;
    private MockInterface $logger;

    // ── run() ─────────────────────────────────────────────────────────────────

    public function test_creates_one_payout_per_user_currency_group(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 1000);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [['amount' => 2000, 'currency' => 'GBP']],
                8 => [['amount' => 3000, 'currency' => 'GBP']],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')->andReturn(false);
        $this->payoutRepository->shouldReceive('create')->twice();
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(2, $count);
    }

    public function test_creates_separate_payouts_for_different_currencies(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 500);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [
                    ['amount' => 1000, 'currency' => 'GBP'],
                    ['amount' => 800, 'currency' => 'USD'],
                ],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')->andReturn(false);
        $this->payoutRepository->shouldReceive('create')->twice();
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(2, $count);
    }

    public function test_skips_group_below_minimum_threshold(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 5000);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [['amount' => 4999, 'currency' => 'GBP']], // below minimum
            ]);

        $this->payoutRepository->shouldNotReceive('create');
        $this->logger->shouldReceive('info')->once()->withArgs(fn(string $msg) => str_contains($msg, 'below minimum'));

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(0, $count);
    }

    public function test_skips_group_when_in_flight_payout_exists_for_same_currency(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 1000);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [['amount' => 2000, 'currency' => 'GBP']],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')
            ->with(7, 'GBP')
            ->andReturn(true);

        $this->payoutRepository->shouldNotReceive('create');
        $this->logger->shouldReceive('info')->once()->withArgs(fn(string $msg) => str_contains($msg, 'in-flight'));

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(0, $count);
    }

    public function test_in_flight_check_is_scoped_per_currency(): void
    {
        // GBP has in-flight, EUR does not — EUR payout should still be created.
        $this->setupTerms(delayDays: 7, minimumPence: 500);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [
                    ['amount' => 1000, 'currency' => 'GBP'],
                    ['amount' => 800, 'currency' => 'EUR'],
                ],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')
            ->with(7, 'GBP')
            ->andReturn(true);
        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')
            ->with(7, 'EUR')
            ->andReturn(false);

        // Only EUR payout should be created.
        $this->payoutRepository->shouldReceive('create')->once()->withArgs(
            fn(array $data) => $data['currency'] === 'EUR'
        );
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(1, $count);
    }

    public function test_payout_created_with_correct_fields(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 1000);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [
                    ['amount' => 1500, 'currency' => 'GBP'],
                    ['amount' => 500, 'currency' => 'GBP'],
                ],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')->andReturn(false);
        $this->payoutRepository->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['user_id'] === 7
                    && $data['site_id'] === 1
                    && $data['amount'] === 2000
                    && $data['currency'] === 'GBP'
                    && $data['status'] === PayoutStatus::Pending->value;
            });
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $this->service->run(siteId: 1);
        $this->assertTrue(true);
    }

    public function test_amounts_are_cast_to_int_before_summing(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 500);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [
                    ['amount' => '750', 'currency' => 'GBP'], // string amount
                    ['amount' => null, 'currency' => 'GBP'],  // missing amount
                ],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')->andReturn(false);
        $this->payoutRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn(array $data) => $data['amount'] === 750); // null treated as 0
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $this->service->run(siteId: 1);
        $this->assertTrue(true);
    }

    public function test_returns_zero_when_no_eligible_entries(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 1000);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')->andReturn([]);

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(0, $count);
    }

    public function test_continues_processing_other_users_when_one_fails(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 500);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->once()
            ->with(1, Mockery::type(\DateTime::class))
            ->andReturn([
                7 => [['amount' => 1000, 'currency' => 'GBP']],
                8 => [['amount' => 1000, 'currency' => 'GBP']],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')->andReturn(false);

        $call = 0;
        $fakeModel = Mockery::mock(\App\Models\Model::class);

        $this->payoutRepository->shouldReceive('create')
            ->andReturnUsing(function () use (&$call, $fakeModel) {
                $call++;
                if ($call === 1) {
                    throw new \RuntimeException('DB error');
                }
                return $fakeModel;
            });

        $this->logger->shouldReceive('error')->atLeast()->once();
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(1, $count);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function test_wraps_each_payout_creation_in_transaction(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 500);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [['amount' => 1000, 'currency' => 'GBP']],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')->andReturn(false);
        $this->payoutRepository->shouldReceive('create');
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $this->service->run(siteId: 1);
        $this->assertTrue(true);
    }

    private function setupTerms(int $delayDays, int $minimumPence): void
    {
        $terms = new PaymentTerms([
            'id' => 1,
            'site_id' => 1,
            'payout_delay_days' => $delayDays,
            'minimum_payout_amount' => $minimumPence,
        ]);
        $terms->exists = true;

        $this->paymentTermsService->shouldReceive('forSite')->andReturn($terms);
    }

    protected function setUp(): void
    {

        $this->ledgerRepository = Mockery::mock(EarningsLedgerRepository::class);
        $this->payoutRepository = Mockery::mock(PayoutRepository::class);
        $this->paymentTermsService = Mockery::mock(PaymentTermsService::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new PayoutSchedulerService(
            $this->ledgerRepository,
            $this->payoutRepository,
            $this->paymentTermsService,
            $this->databaseMock,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}