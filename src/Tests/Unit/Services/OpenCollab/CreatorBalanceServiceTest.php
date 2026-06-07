<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Repositories\OpenCollab\CreatorLiabilityRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\CreatorBalanceService;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class CreatorBalanceServiceTest extends TestCase
{
    private CreatorBalanceService $service;
    private MockInterface $ledgerRepository;
    private MockInterface $liabilityRepository;
    private MockInterface $payoutRepository;

    public function test_returns_state_balances_for_creator(): void
    {
        $this->ledgerRepository
            ->shouldReceive('balancesByStatusForContributor')
            ->with(7)
            ->once()
            ->andReturn([
                AccrualStatus::Estimated->value => 1000,
                AccrualStatus::Confirmed->value => 2000,
                AccrualStatus::Settled->value => 8000,
                AccrualStatus::Withdrawn->value => 3000,
                AccrualStatus::Reversed->value => -500,
            ]);

        $this->liabilityRepository
            ->shouldReceive('openAmountForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(1500);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7)
            ->once()
            ->andReturn(1000);

        $balances = $this->service->balances(7, 1);

        $this->assertSame(1000, $balances['estimated_balance']);
        $this->assertSame(2000, $balances['confirmed_balance']);
        $this->assertSame(8000, $balances['settled_balance']);
        $this->assertSame(3000, $balances['withdrawn_balance']);
        $this->assertSame(-500, $balances['reversed_balance']);
        $this->assertSame(1500, $balances['open_liabilities']);
        $this->assertSame(1000, $balances['in_flight_payouts']);
        $this->assertSame(5500, $balances['available_to_withdraw']);
    }

    public function test_available_to_withdraw_is_settled_minus_liabilities_minus_in_flight(): void
    {
        $this->ledgerRepository
            ->shouldReceive('balancesByStatusForContributor')
            ->andReturn([
                AccrualStatus::Estimated->value => 999999,
                AccrualStatus::Confirmed->value => 999999,
                AccrualStatus::Settled->value => 10000,
                AccrualStatus::Withdrawn->value => 0,
                AccrualStatus::Reversed->value => 0,
            ]);

        $this->liabilityRepository
            ->shouldReceive('openAmountForContributor')
            ->with(7, 1)
            ->andReturn(2500);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7)
            ->andReturn(1500);

        $this->assertSame(6000, $this->service->availableToWithdraw(7, 1));
    }

    public function test_available_to_withdraw_never_goes_negative(): void
    {
        $this->ledgerRepository
            ->shouldReceive('balancesByStatusForContributor')
            ->andReturn([
                AccrualStatus::Estimated->value => 0,
                AccrualStatus::Confirmed->value => 0,
                AccrualStatus::Settled->value => 1000,
                AccrualStatus::Withdrawn->value => 0,
                AccrualStatus::Reversed->value => 0,
            ]);

        $this->liabilityRepository
            ->shouldReceive('openAmountForContributor')
            ->andReturn(2000);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->andReturn(1000);

        $this->assertSame(0, $this->service->availableToWithdraw(7, 1));
    }

    public function test_estimated_and_confirmed_balances_do_not_affect_available_withdrawal(): void
    {
        $this->ledgerRepository
            ->shouldReceive('balancesByStatusForContributor')
            ->andReturn([
                AccrualStatus::Estimated->value => 50000,
                AccrualStatus::Confirmed->value => 50000,
                AccrualStatus::Settled->value => 0,
                AccrualStatus::Withdrawn->value => 0,
                AccrualStatus::Reversed->value => 0,
            ]);

        $this->liabilityRepository
            ->shouldReceive('openAmountForContributor')
            ->andReturn(0);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->andReturn(0);

        $this->assertSame(0, $this->service->availableToWithdraw(7, 1));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerRepository = Mockery::mock(EarningsLedgerRepository::class);
        $this->liabilityRepository = Mockery::mock(CreatorLiabilityRepository::class);
        $this->payoutRepository = Mockery::mock(PayoutRepository::class);

        $this->service = new CreatorBalanceService(
            $this->ledgerRepository,
            $this->liabilityRepository,
            $this->payoutRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}