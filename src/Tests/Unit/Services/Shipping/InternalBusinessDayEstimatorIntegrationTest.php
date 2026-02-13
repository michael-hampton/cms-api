<?php

namespace App\Tests\Unit\Services\Shipping;

use App\DTO\Checkout\DeliveryMethodConfig;
use App\Services\Shipping\BusinessDayCalculator;
use App\Services\Shipping\CutOffTimeResolver;
use App\Services\Shipping\FulfilmentTypeInterface;
use App\Services\Shipping\InternalBusinessDayEstimator;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class InternalBusinessDayEstimatorIntegrationTest extends FunctionalTestCase
{
    private $calculator;
    private $cutOffResolver;
    private InternalBusinessDayEstimator $estimator;

    public function testDigitalSubscriptionNeverCallsCalculator(): void
    {
        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $fulfilment->shouldReceive('requiresShipping')->andReturn(false);

        $deliveryMethod = new DeliveryMethodConfig('14:00', 2, 5);
        $orderDate = new \DateTimeImmutable();

        $this->calculator->shouldNotReceive('addBusinessDays');
        $this->cutOffResolver->shouldNotReceive('resolveStartDate');

        $result = $this->estimator->estimate($fulfilment, $deliveryMethod, $orderDate);

        $this->assertFalse($result->requiresShipping);
        $this->assertNull($result->dispatchDate);
    }

    public function testDeliveryMethodMinEqualsMaxDays(): void
    {
        $orderDate = new \DateTimeImmutable('2026-02-13 10:00:00');
        $startDate = new \DateTimeImmutable('2026-02-13');
        $dispatchDate = new \DateTimeImmutable('2026-02-17');
        $deliveryDate = new \DateTimeImmutable('2026-02-19');

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $fulfilment->shouldReceive('requiresShipping')->andReturn(true);
        $fulfilment->shouldReceive('dispatchDays')->andReturn(2);

        $deliveryMethod = new DeliveryMethodConfig('14:00', 2, 2);

        $this->cutOffResolver->shouldReceive('resolveStartDate')
            ->with($orderDate, '14:00')
            ->andReturn($startDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->with($startDate, 2)
            ->andReturn($dispatchDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->with($dispatchDate, 2)
            ->twice()
            ->andReturn($deliveryDate);

        $result = $this->estimator->estimate($fulfilment, $deliveryMethod, $orderDate);

        $this->assertEquals($result->from->format('Y-m-d'), $result->to->format('Y-m-d'));
        $this->assertEquals('2026-02-19', $result->from->format('Y-m-d'));
    }

    public function testPrintedSubscriptionWithFallbackDispatchDefault(): void
    {
        $orderDate = new \DateTimeImmutable('2026-02-13 10:00:00');
        $startDate = new \DateTimeImmutable('2026-02-13');
        $dispatchDate = new \DateTimeImmutable('2026-02-18');

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $fulfilment->shouldReceive('requiresShipping')->andReturn(true);
        $fulfilment->shouldReceive('dispatchDays')->andReturn(3);

        $deliveryMethod = new DeliveryMethodConfig('14:00', 2, 5);

        $this->cutOffResolver->shouldReceive('resolveStartDate')
            ->andReturn($startDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->with($startDate, 3)
            ->andReturn($dispatchDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->andReturn(new \DateTimeImmutable());

        $result = $this->estimator->estimate($fulfilment, $deliveryMethod, $orderDate);

        $this->assertEquals('2026-02-18', $result->dispatchDate->format('Y-m-d'));
    }

    public function testMixedCartBehaviourWithMultipleItems(): void
    {
        $orderDate = new \DateTimeImmutable('2026-02-13 10:00:00');

        $physicalFulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $physicalFulfilment->shouldReceive('requiresShipping')->andReturn(true);
        $physicalFulfilment->shouldReceive('dispatchDays')->andReturn(2);

        $digitalFulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $digitalFulfilment->shouldReceive('requiresShipping')->andReturn(false);

        $deliveryMethod = new DeliveryMethodConfig('14:00', 2, 5);

        $this->cutOffResolver->shouldReceive('resolveStartDate')
            ->once()
            ->andReturn($orderDate);

        $this->calculator->shouldReceive('addBusinessDays')
            ->andReturn(new \DateTimeImmutable('2026-02-17'));

        $physicalResult = $this->estimator->estimate($physicalFulfilment, $deliveryMethod, $orderDate);
        $this->assertTrue($physicalResult->requiresShipping);
        $this->assertNotNull($physicalResult->dispatchDate);

        $this->calculator->shouldNotReceive('addBusinessDays')->times(0);
        $digitalResult = $this->estimator->estimate($digitalFulfilment, $deliveryMethod, $orderDate);
        $this->assertFalse($digitalResult->requiresShipping);
        $this->assertNull($digitalResult->dispatchDate);
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