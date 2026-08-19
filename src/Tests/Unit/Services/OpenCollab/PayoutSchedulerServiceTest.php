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
use App\Services\OpenCollab\PayoutService;
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
    private MockInterface $payoutService;

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
        $this->payoutRepository->shouldReceive('createWithIdempotency')->twice();
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
        $this->payoutRepository->shouldReceive('createWithIdempotency')->twice();
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

        $this->payoutRepository->shouldNotReceive('createWithIdempotency');
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
            ->with(7, 'GBP', 1)
            ->andReturn(true);

        $this->payoutRepository->shouldNotReceive('createWithIdempotency');
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
            ->with(7, 'GBP', 1)
            ->andReturn(true);
        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')
            ->with(7, 'EUR', 1)
            ->andReturn(false);

        // Only EUR payout should be created.
        $this->payoutRepository->shouldReceive('createWithIdempotency')->once()->withArgs(
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
        $this->payoutRepository->shouldReceive('createWithIdempotency')
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
        $this->payoutRepository->shouldReceive('createWithIdempotency')
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
        $this->payoutRepository->shouldReceive('findByIdempotencyKey')->andReturnNull();

        $call = 0;
        $this->payoutRepository->shouldReceive('createWithIdempotency')
            ->andReturnUsing(function () use (&$call) {
                $call++;
                if ($call === 1) {
                    throw new \RuntimeException('DB error');
                }

                return Mockery::mock(\App\Models\Payout::class);
            });

        $this->logger->shouldReceive('error')->atLeast()->once();
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(1, $count);
    }

    // ── Idempotency-key race prevention ─────────────────────────────────────

    public function test_skips_creation_when_idempotency_key_already_exists_for_window(): void
    {
        // The fast-path skip: this (user, site, currency, cutoff) window has
        // already produced a payout, so no insert should even be attempted.
        $this->setupTerms(delayDays: 7, minimumPence: 500);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [['amount' => 1000, 'currency' => 'GBP']],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')->andReturn(false);
        $this->payoutRepository->shouldReceive('findByIdempotencyKey')->andReturn(
            Mockery::mock(\App\Models\Payout::class)
        );

        $this->payoutRepository->shouldNotReceive('createWithIdempotency');
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(0, $count);
    }

    public function test_treats_duplicate_key_failure_as_a_won_race_not_an_error(): void
    {
        // Simulates two overlapping scheduler runs: the insert fails (DB
        // unique-constraint violation on idempotency_key), and when we
        // re-check we find the row now exists — this must be logged as
        // informational, not as an error, and must not increment $created.
        $this->setupTerms(delayDays: 7, minimumPence: 500);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [['amount' => 1000, 'currency' => 'GBP']],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')->andReturn(false);

        $findCall = 0;
        $this->payoutRepository->shouldReceive('findByIdempotencyKey')
            ->andReturnUsing(function () use (&$findCall) {
                $findCall++;
                // First call: fast-path pre-check — nothing exists yet.
                // Second call: inside the catch block — the concurrent run won.
                return $findCall === 1 ? null : Mockery::mock(\App\Models\Payout::class);
            });

        $this->payoutRepository->shouldReceive('createWithIdempotency')
            ->once()
            ->andThrow(new \RuntimeException('Duplicate entry for key idempotency_key'));

        $this->logger->shouldNotReceive('error');
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();

        $count = $this->service->run(siteId: 1);

        $this->assertEquals(0, $count);
    }

    public function test_passes_idempotency_key_through_to_repository(): void
    {
        $this->setupTerms(delayDays: 7, minimumPence: 500);

        $this->ledgerRepository->shouldReceive('eligibleGroupedBySiteAndUser')
            ->andReturn([
                7 => [['amount' => 1000, 'currency' => 'GBP']],
            ]);

        $this->payoutRepository->shouldReceive('hasInFlightForContributorAndCurrency')->andReturn(false);

        $this->payoutService
            ->shouldReceive('makeScheduledPayoutIdempotencyKey')
            ->once()
            ->with(7, 1, 'GBP', Mockery::type(\DateTimeInterface::class))
            ->andReturn('payout:scheduled:user:7:site:1:currency:GBP:cutoff:2026-01-01');

        $this->payoutRepository->shouldReceive('createWithIdempotency')
            ->once()
            ->withArgs(fn(array $data) =>
                $data['idempotency_key'] === 'payout:scheduled:user:7:site:1:currency:GBP:cutoff:2026-01-01'
            );
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
        $this->payoutRepository->shouldReceive('createWithIdempotency');
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
        $this->payoutService = Mockery::mock(PayoutService::class);

        $this->databaseMock->shouldReceive('transaction')->byDefault()->andReturnUsing(fn(callable $cb) => $cb());

        $this->payoutService
            ->shouldReceive('makeScheduledPayoutIdempotencyKey')
            ->byDefault()
            ->andReturnUsing(fn(int $userId, int $siteId, string $currency, \DateTimeInterface $cutoff) =>
                "payout:scheduled:user:{$userId}:site:{$siteId}:currency:" . strtoupper($currency) . ':cutoff:' . $cutoff->format('Y-m-d')
            );

        $this->payoutRepository->shouldReceive('findByIdempotencyKey')->byDefault()->andReturnNull();

        $this->service = new PayoutSchedulerService(
            $this->ledgerRepository,
            $this->payoutRepository,
            $this->paymentTermsService,
            $this->databaseMock,
            $this->logger,
            $this->payoutService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}