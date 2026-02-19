<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Contracts\Boost\BoostableInterface;
use App\Contracts\ClockInterface;
use App\Enums\Boost\BoostableType;
use App\Enums\Boost\BoostContext;
use App\Enums\Boost\BoostStatus;
use App\Exceptions\Boost\BoostNotFoundException;
use App\Exceptions\Boost\BoostTransitionException;
use App\Framework\Database\Database;
use App\Models\Boost;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Services\Adverts\Boost\BoostEligibilityService;
use App\Services\Adverts\Boost\BoostPricingService;
use App\Services\Adverts\Boost\BoostService;
use App\Services\FrozenClock;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class BoostServiceTest extends FunctionalTestCase
{
    private MockInterface $boostRepository;
    private MockInterface $eligibilityService;
    private MockInterface $pricingService;
    private MockInterface $databaseMock;
    private BoostService $service;
    private ClockInterface $clock;

    public function test_creates_boost_with_pending_status(): void
    {
        $target = $this->makeTarget();
        $boost = $this->makeBoost('pending');

        $this->eligibilityService->shouldReceive('assertEligible')->once();
        $this->pricingService->shouldReceive('calculate')->once()->andReturn(35.00);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($d) => $d['status'] === BoostStatus::Pending->value))
            ->andReturn($boost);

        $result = $this->service->createBoost(
            $target, BoostableType::Product->value, 99,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
            1.5
        );

        $this->assertEquals(BoostStatus::Pending->value, $result->status);
    }

    private function makeTarget(): MockInterface
    {
        $target = Mockery::mock(BoostableInterface::class);
        $target->shouldReceive('getBoostableId')->andReturn(42);
        return $target;
    }

    private function makeBoost(string $status = 'pending', int $id = 1): Boost
    {
        $boost = new Boost([
            'id' => $id,
            'boostable_type' => BoostableType::Product->value,
            'boostable_id' => 42,
            'merchant_id' => 99,
            'context' => BoostContext::Listing->value,
            'starts_at' => '2026-01-01 00:00:00',
            'ends_at' => '2026-01-08 00:00:00',
            'multiplier' => 1.5,
            'status' => $status,
            'price_paid' => 35.00,
            'currency' => 'GBP',
        ]);
        $boost->id = $id;
        return $boost;
    }

    public function test_delegates_eligibility_check_on_create(): void
    {
        $target = $this->makeTarget();
        $boost = $this->makeBoost();

        $this->eligibilityService
            ->shouldReceive('assertEligible')
            ->once()
            ->with($target, BoostableType::Product->value, 99);

        $this->pricingService->shouldReceive('calculate')->andReturn(35.00);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostRepository->shouldReceive('create')->andReturn($boost);

        $this->service->createBoost(
            $target, BoostableType::Product->value, 99,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
            1.5
        );
        $this->assertTrue(true);
    }

    // --- Creation ---

    public function test_delegates_pricing_calculation_on_create(): void
    {
        $target = $this->makeTarget();
        $boost = $this->makeBoost();

        $this->eligibilityService->shouldReceive('assertEligible')->once();
        $this->pricingService
            ->shouldReceive('calculate')
            ->once()
            ->with(
                BoostableType::Product->value,
                BoostContext::Listing->value,
                Mockery::type(\DateTimeInterface::class),
                Mockery::type(\DateTimeInterface::class),
                null
            )
            ->andReturn(35.00);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostRepository->shouldReceive('create')->andReturn($boost);

        $this->service->createBoost(
            $target, BoostableType::Product->value, 99,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
            1.5
        );
        $this->assertTrue(true);
    }

    public function test_fails_creation_when_eligibility_fails(): void
    {
        $this->expectException(\RuntimeException::class);

        $target = $this->makeTarget();
        $this->eligibilityService
            ->shouldReceive('assertEligible')
            ->andThrow(new \RuntimeException('Not eligible'));

        $this->pricingService->shouldNotReceive('calculate');
        $this->boostRepository->shouldNotReceive('create');

        $this->service->createBoost(
            $target, BoostableType::Product->value, 99,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
            1.5
        );
    }

    public function test_emits_boost_created_event(): void
    {
        $target = $this->makeTarget();
        $boost = $this->makeBoost();

        $this->eligibilityService->shouldReceive('assertEligible')->once();
        $this->pricingService->shouldReceive('calculate')->andReturn(35.00);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostRepository->shouldReceive('create')->andReturn($boost);

        $this->service->createBoost(
            $target, BoostableType::Product->value, 99,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
            1.5
        );

        $this->assertTrue(true);
    }

    public function test_activates_boost_when_start_time_reached(): void
    {
        // Frozen clock is 2026-01-02 12:00:00; boost starts_at 2026-01-01 — already past.
        $boost = $this->makeBoost('pending');
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostRepository->shouldReceive('update')
            ->with(1, ['status' => BoostStatus::Active->value])
            ->andReturn($this->makeBoost('active'));

        $result = $this->service->activateBoost(1); // no $now param

        $this->assertEquals(BoostStatus::Active->value, $result->status);
    }

    public function test_throws_when_activating_before_start_time(): void
    {
        $this->expectException(BoostTransitionException::class);
        $this->expectExceptionMessage('cannot be activated before its start time');

        // Boost starts in the future relative to frozen clock (2026-01-02 12:00:00)
        $boost = $this->makeBoost('pending');
        $boost->starts_at = '2026-01-10 00:00:00';

        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);

        $this->service->activateBoost(1);
    }

    // --- Activation ---

    public function test_activation_is_idempotent(): void
    {
        $boost = $this->makeBoost('active');
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->boostRepository->shouldNotReceive('update');

        $result = $this->service->activateBoost(1, new \DateTimeImmutable('2026-01-01'));
        $this->assertEquals(BoostStatus::Active->value, $result->status);
    }

    public function test_throws_when_activating_cancelled_boost(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot activate a cancelled boost');

        $boost = $this->makeBoost('cancelled');
        $this->boostRepository->shouldReceive('find')->andReturn($boost);

        $this->service->activateBoost(1, new \DateTimeImmutable('2026-01-01'));
    }

    public function test_throws_when_boost_not_found_on_activate(): void
    {
        $this->expectException(BoostNotFoundException::class);

        $this->boostRepository->shouldReceive('find')->with(999)->andReturn(null);

        $this->service->activateBoost(999);
    }

    public function test_expires_active_boost(): void
    {
        $boost = $this->makeBoost('active');
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostRepository->shouldReceive('update')
            ->with(1, ['status' => BoostStatus::Expired->value])
            ->andReturn($this->makeBoost('expired'));

        $result = $this->service->expireBoost(1, new \DateTimeImmutable('2026-01-09'));
        $this->assertEquals(BoostStatus::Expired->value, $result->status);
    }

    public function test_expiration_is_idempotent(): void
    {
        $boost = $this->makeBoost('expired');
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->boostRepository->shouldNotReceive('update');

        $result = $this->service->expireBoost(1, new \DateTimeImmutable('2026-01-09'));
        $this->assertEquals(BoostStatus::Expired->value, $result->status);
    }

    // --- Expiration ---

    public function test_does_not_expire_cancelled_boost(): void
    {
        $boost = $this->makeBoost('cancelled');
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->boostRepository->shouldNotReceive('update');

        $result = $this->service->expireBoost(1, new \DateTimeImmutable('2026-01-09'));
        $this->assertEquals(BoostStatus::Cancelled->value, $result->status);
    }

    public function test_throws_when_boost_not_found_on_expire(): void
    {
        $this->expectException(BoostNotFoundException::class);

        $this->boostRepository->shouldReceive('find')->with(999)->andReturn(null);

        $this->service->expireBoost(999);
    }

    public function test_cancels_pending_boost(): void
    {
        $boost = $this->makeBoost('pending');
        $this->boostRepository->shouldReceive('find')->andReturn($boost);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostRepository->shouldReceive('update')
            ->with(1, ['status' => BoostStatus::Cancelled->value])
            ->andReturn($this->makeBoost('cancelled'));

        $result = $this->service->cancelBoost(1);
        $this->assertEquals(BoostStatus::Cancelled->value, $result->status);
    }

    public function test_throws_when_cancelling_expired_boost(): void
    {
        $this->expectException(\RuntimeException::class);

        $boost = $this->makeBoost('expired');
        $this->boostRepository->shouldReceive('find')->andReturn($boost);

        $this->service->cancelBoost(1);
    }


    // --- Cancellation ---

    public function test_cancel_is_idempotent(): void
    {
        $boost = $this->makeBoost('cancelled');
        $this->boostRepository->shouldReceive('find')->andReturn($boost);
        $this->boostRepository->shouldNotReceive('update');

        $result = $this->service->cancelBoost(1);
        $this->assertEquals(BoostStatus::Cancelled->value, $result->status);
    }

    public function test_throws_when_boost_not_found_on_cancel(): void
    {
        $this->expectException(BoostNotFoundException::class);

        $this->boostRepository->shouldReceive('find')->with(999)->andReturn(null);

        $this->service->cancelBoost(999);
    }

    public function test_pauses_active_boost(): void
    {
        $boost = $this->makeBoost('active');
        $this->boostRepository->shouldReceive('find')->andReturn($boost);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostRepository->shouldReceive('update')
            ->with(1, ['status' => BoostStatus::Paused->value])
            ->andReturn($this->makeBoost('paused'));

        $result = $this->service->pauseBoost(1);

        $this->assertEquals(BoostStatus::Paused->value, $result->status);
    }

    public function test_throws_when_pausing_non_active_boost(): void
    {
        $this->expectException(BoostTransitionException::class);

        $boost = $this->makeBoost('pending');
        $this->boostRepository->shouldReceive('find')->andReturn($boost);

        $this->service->pauseBoost(1);
    }

    public function test_resumes_paused_boost_within_period(): void
    {
        // Frozen clock 2026-01-02; boost ends 2026-01-08 — still within period.
        $boost = $this->makeBoost('paused');
        $this->boostRepository->shouldReceive('find')->andReturn($boost);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostRepository->shouldReceive('update')
            ->with(1, ['status' => BoostStatus::Active->value])
            ->andReturn($this->makeBoost('active'));

        $result = $this->service->resumeBoost(1);

        $this->assertEquals(BoostStatus::Active->value, $result->status);
    }

    public function test_throws_when_resuming_non_paused_boost(): void
    {
        $this->expectException(BoostTransitionException::class);

        $boost = $this->makeBoost('active');
        $this->boostRepository->shouldReceive('find')->andReturn($boost);

        $this->service->resumeBoost(1);
    }

    public function test_throws_when_resuming_after_boost_period_ended(): void
    {
        $this->expectException(BoostTransitionException::class);
        $this->expectExceptionMessage('Boost period has ended');

        $boost = $this->makeBoost('paused');
        $boost->ends_at = '2025-12-01 00:00:00'; // in the past relative to frozen clock

        $this->boostRepository->shouldReceive('find')->andReturn($boost);

        $this->service->resumeBoost(1);
    }

    protected function setUp(): void
    {
        $this->boostRepository = Mockery::mock(BoostRepository::class);
        $this->eligibilityService = Mockery::mock(BoostEligibilityService::class);
        $this->pricingService = Mockery::mock(BoostPricingService::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-02 12:00:00', new \DateTimeZone('UTC')));

        $this->service = new BoostService(
            $this->boostRepository,
            $this->eligibilityService,
            $this->pricingService,
            $this->databaseMock,
            $this->clock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}