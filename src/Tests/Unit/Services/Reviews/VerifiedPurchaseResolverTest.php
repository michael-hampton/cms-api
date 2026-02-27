<?php

namespace App\Tests\Unit\Services\Reviews;

use App\Repositories\Billing\OrderRepository;
use App\Services\Reviews\VerifiedPurchaseResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class VerifiedPurchaseResolverTest extends TestCase
{
    private VerifiedPurchaseResolver $resolver;
    private OrderRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->resolver = new VerifiedPurchaseResolver($this->orderRepository);
    }

    public function testIsVerifiedAlwaysReturnsFalse()
    {
        $this->orderRepository->shouldReceive('getByUser')->andReturn(collect());
        // TODO implementation pending order system
        $result = $this->resolver->isVerified(123, 1);
        $this->assertFalse($result);
    }

    public function testIsVerifiedWithDifferentParameters()
    {
        $this->orderRepository->shouldReceive('getByUser')->andReturn(collect());

        $this->assertFalse($this->resolver->isVerified(1, 1));
        $this->assertFalse($this->resolver->isVerified(999, 999));
    }
}