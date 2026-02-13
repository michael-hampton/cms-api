<?php

namespace App\Tests\Unit\Services\Shipping;

use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Services\Shipping\DigitalSubscriptionFulfilment;
use App\Services\Shipping\FulfilmentResolver;
use App\Services\Shipping\PhysicalProductFulfilment;
use App\Services\Shipping\PrintedSubscriptionFulfilment;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use DomainException;
use Mockery;

class FulfilmentResolverTest extends FunctionalTestCase
{
    private FulfilmentResolver $resolver;

    public function testResolvesPhysicalProduct(): void
    {
        $product = Mockery::mock(Product::class);

        $result = $this->resolver->resolve($product);

        $this->assertInstanceOf(PhysicalProductFulfilment::class, $result);
    }

    public function testResolvesDigitalSubscription(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = false;
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);
        $plan->shouldReceive('getAttribute')->with('print_shipping_required')->andReturn(false);

        $result = $this->resolver->resolve($plan);

        $this->assertInstanceOf(DigitalSubscriptionFulfilment::class, $result);
    }

    public function testResolvesPrintedSubscription(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class);
        $plan->shouldReceive('hasDigitalOption')->andReturn(false);
        $plan->shouldReceive('getAttribute')->with('print_shipping_required')->andReturn(true);

        $result = $this->resolver->resolve($plan);

        $this->assertInstanceOf(PrintedSubscriptionFulfilment::class, $result);
    }

    public function testThrowsExceptionForUnsupportedType(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unsupported purchasable type');

        $this->resolver->resolve(new \stdClass());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FulfilmentResolver();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}