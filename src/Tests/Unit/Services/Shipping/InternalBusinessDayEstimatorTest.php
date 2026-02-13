<?php

namespace App\Tests\Unit\Services\Shipping;

use App\DTO\Checkout\DeliveryMethodConfig;
use App\Services\Shipping\BusinessDayCalculator;
use App\Services\Shipping\CutOffTimeResolver;
use App\Services\Shipping\FulfilmentTypeInterface;
use App\Services\Shipping\InternalBusinessDayEstimator;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class InternalBusinessDayEstimatorTest extends FunctionalTestCase
{
    private $calculator;
    private $cutOffResolver;
    private InternalBusinessDayEstimator $estimator;

    public function testDigitalItemReturnsInstantDelivery(): void
    {
        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $fulfilment->shouldReceive('requiresShipping')->andReturn(false);

        $deliveryMethod = new DeliveryMethodConfig('14:00', 1, 5);
        $orderDate = new \DateTimeImmutable();

        $result = $this->estimator->estimate($fulfilment, $deliveryMethod, $orderDate);

        $this->assertFalse($result->requiresShipping);
        $this->assertNull($result->dispatchDate);
        $this->assertEquals('Available immediately after payment', $result->formattedRange());
    }

    public function testNormalWeekdayOrder(): void
    {
        $orderDate = new \DateTimeImmutable('2026-02-13 10:00:00');
        $startDate = new \DateTimeImmutable('2026-02-13');
        $dispatchDate = new \DateTimeImmutable('2026-02-17');
        $deliveryFrom = new \DateTimeImmutable('2026-02-19');
        $deliveryTo = new \DateTimeImmutable('2026-02-24');

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $fulfilment->shouldReceive('requiresShipping')->andReturn(true);
        $fulfilment->shouldReceive('dispatchDays')->andReturn(2);

        $deliveryMethod = new DeliveryMethodConfig('14:00', 2, 5);

        $this->cutOffResolver->shouldReceive('resolveStartDate')
            ->with($orderDate, '14:00')
            ->andReturn($startDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->with($startDate, 2)
            ->andReturn($dispatchDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->with($dispatchDate, 2)
            ->andReturn($deliveryFrom);

        $this->calculator->shouldReceive('addBusinessDays')
            ->with($dispatchDate, 5)
            ->andReturn($deliveryTo);

        $result = $this->estimator->estimate($fulfilment, $deliveryMethod, $orderDate);

        $this->assertTrue($result->requiresShipping);
        $this->assertEquals('2026-02-17', $result->dispatchDate->format('Y-m-d'));
        $this->assertEquals('2026-02-19', $result->from->format('Y-m-d'));
        $this->assertEquals('2026-02-24', $result->to->format('Y-m-d'));
    }

    public function testOrderAfterCutOff(): void
    {
        $orderDate = new \DateTimeImmutable('2026-02-13 15:00:00');
        $startDate = new \DateTimeImmutable('2026-02-14');
        $dispatchDate = new \DateTimeImmutable('2026-02-18');

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $fulfilment->shouldReceive('requiresShipping')->andReturn(true);
        $fulfilment->shouldReceive('dispatchDays')->andReturn(2);

        $deliveryMethod = new DeliveryMethodConfig('14:00', 2, 5);

        $this->cutOffResolver->shouldReceive('resolveStartDate')
            ->with($orderDate, '14:00')
            ->andReturn($startDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->with($startDate, 2)
            ->andReturn($dispatchDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->andReturn(new \DateTimeImmutable());

        $result = $this->estimator->estimate($fulfilment, $deliveryMethod, $orderDate);

        $this->assertTrue($result->requiresShipping);
        $this->assertEquals('2026-02-18', $result->dispatchDate->format('Y-m-d'));
    }

    public function testFridayOrderWithWeekendSkip(): void
    {
        $orderDate = new \DateTimeImmutable('2026-02-13 10:00:00');
        $startDate = new \DateTimeImmutable('2026-02-13');
        $dispatchDate = new \DateTimeImmutable('2026-02-17');

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $fulfilment->shouldReceive('requiresShipping')->andReturn(true);
        $fulfilment->shouldReceive('dispatchDays')->andReturn(2);

        $deliveryMethod = new DeliveryMethodConfig('14:00', 2, 5);

        $this->cutOffResolver->shouldReceive('resolveStartDate')
            ->andReturn($startDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->with($startDate, 2)
            ->andReturn($dispatchDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->andReturn(new \DateTimeImmutable());

        $result = $this->estimator->estimate($fulfilment, $deliveryMethod, $orderDate);

        $this->assertEquals('2026-02-17', $result->dispatchDate->format('Y-m-d'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = Mockery::mock(BusinessDayCalculator::class);
        $this->cutOffResolver = Mockery::mock(CutOffTimeResolver::class);
        $this->estimator = new InternalBusinessDayEstimator(
            $this->calculator,
            $this->cutOffResolver
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}