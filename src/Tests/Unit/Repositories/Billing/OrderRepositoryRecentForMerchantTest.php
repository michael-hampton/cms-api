<?php

namespace App\Tests\Unit\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\Merchant;
use App\Repositories\Billing\OrderRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OrderRepositoryRecentForMerchantTest extends FunctionalTestCase
{
    use CreatesTestData;

    private OrderRepository $repository;
    private Merchant $merchant;

    public function test_returns_empty_collection_when_merchant_has_no_orders(): void
    {
        $result = $this->repository->recentForMerchant($this->merchant->id, limit: 5);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_returns_orders_belonging_to_the_merchant(): void
    {
        $this->createOrder(['merchant_id' => $this->merchant->id]);
        $this->createOrder(['merchant_id' => $this->merchant->id]);

        $result = $this->repository->recentForMerchant($this->merchant->id, limit: 10);

        $this->assertCount(2, $result);
        foreach ($result as $order) {
            $this->assertSame($this->merchant->id, (int)$order->merchant_id);
        }
    }

    public function test_does_not_return_orders_from_other_merchants(): void
    {
        $other = $this->createMerchant();
        $this->createOrder(['merchant_id' => $other->id]);

        $result = $this->repository->recentForMerchant($this->merchant->id, limit: 10);

        $this->assertTrue($result->isEmpty());
    }

    public function test_respects_limit(): void
    {
        foreach (range(1, 6) as $_) {
            $this->createOrder(['merchant_id' => $this->merchant->id]);
        }

        $result = $this->repository->recentForMerchant($this->merchant->id, limit: 4);

        $this->assertCount(4, $result);
    }

    public function test_orders_are_returned_newest_first(): void
    {
        $first = $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);
        $second = $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $third = $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->repository->recentForMerchant($this->merchant->id, limit: 3);
        $ids = $result->pluck('id')->toArray();

        $this->assertSame([$first->id, $second->id, $third->id], $ids);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new OrderRepository();
        $this->merchant = $this->createMerchant();
    }
}