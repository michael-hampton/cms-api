<?php

namespace App\Tests\Unit\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\Merchant;
use App\Repositories\Billing\OrderRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OrderRepositoryDailyRevenueTest extends FunctionalTestCase
{
    use CreatesTestData;

    private OrderRepository $repository;
    private Merchant $merchant;

    public function test_returns_collection(): void
    {
        $result = $this->repository->dailyRevenueForMerchant($this->merchant->id, days: 7);

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_always_returns_exactly_n_days(): void
    {
        $result = $this->repository->dailyRevenueForMerchant($this->merchant->id, days: 30);

        $this->assertCount(30, $result);
    }

    public function test_days_with_no_orders_have_zero_revenue(): void
    {
        // No orders created — every day should be zero
        $result = $this->repository->dailyRevenueForMerchant($this->merchant->id, days: 7);

        foreach ($result as $row) {
            $this->assertSame(0.0, $row['revenue']);
        }
    }

    public function test_revenue_for_today_reflects_completed_paid_orders(): void
    {
        $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 150.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
        $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 50.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->repository->dailyRevenueForMerchant($this->merchant->id, days: 7);
        $today = date('Y-m-d');
        $todayRow = $result->firstWhere('day', $today);

        $this->assertNotNull($todayRow);
        $this->assertSame(200.00, $todayRow['revenue']);
    }

    public function test_excludes_non_completed_orders_from_series(): void
    {
        $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 999.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->repository->dailyRevenueForMerchant($this->merchant->id, days: 7);
        $today = date('Y-m-d');
        $todayRow = $result->firstWhere('day', $today);

        $this->assertSame(0.0, $todayRow['revenue']);
    }

    public function test_each_row_has_day_and_revenue_keys(): void
    {
        $result = $this->repository->dailyRevenueForMerchant($this->merchant->id, days: 3);

        foreach ($result as $row) {
            $this->assertArrayHasKey('day', $row);
            $this->assertArrayHasKey('revenue', $row);
        }
    }

    public function test_series_is_ordered_oldest_to_newest(): void
    {
        $result = $this->repository->dailyRevenueForMerchant($this->merchant->id, days: 7);
        $days = $result->pluck('day')->toArray();

        $sorted = $days;
        sort($sorted);

        $this->assertSame($sorted, $days);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new OrderRepository();
        $this->merchant = $this->createMerchant();
    }
}