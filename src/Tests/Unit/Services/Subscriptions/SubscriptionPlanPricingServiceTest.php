<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Services\Subscriptions\SubscriptionPlanPricingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class SubscriptionPlanPricingServiceTest extends FunctionalTestCase
{
    private $pricingRepository;
    private $service;
    private $databaseMock;

    public function testGetPricingTiersForPlan(): void
    {
        $planId = 1;
        $tiers = Mockery::mock(Collection::class);

        $this->pricingRepository->shouldReceive('getForPlan')
            ->with($planId)
            ->once()
            ->andReturn($tiers);

        $result = $this->service->getPricingTiersForPlan($planId);

        $this->assertSame($tiers, $result);
    }

    public function testGetDefaultPricingForPlan(): void
    {
        $planId = 1;
        $pricing = Mockery::mock(SubscriptionPlanPricing::class);

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->with($planId)
            ->once()
            ->andReturn($pricing);

        $result = $this->service->getDefaultPricingForPlan($planId);

        $this->assertSame($pricing, $result);
    }

    public function testCreatePricingTierWithTransaction(): void
    {
        $data = [
            'plan_id' => 1,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 99.99,
            'original_price' => 120.00,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 1
        ];

        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->id = 1;

        $this->setTransactionExpectations();

        $this->pricingRepository->shouldReceive('create')
            ->with(Mockery::on(function ($prepared) {
                return $prepared['plan_id'] === 1
                    && $prepared['duration_months'] === 12
                    && $prepared['price'] === 99.99
                    && $prepared['is_active'] === true;
            }))
            ->once()
            ->andReturn($pricing);

        $result = $this->service->createPricingTier($data);

        $this->assertSame($pricing, $result);
    }

    private function setTransactionExpectations(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

    }

    public function testCreatePricingTierSetsAsDefault(): void
    {
        $data = [
            'plan_id' => 1,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 99.99,
            'is_default' => true
        ];

        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->id = 1;

        $this->setTransactionExpectations();

        $this->pricingRepository->shouldReceive('create')
            ->once()
            ->andReturn($pricing);

        $this->pricingRepository->shouldReceive('setAsDefault')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->service->createPricingTier($data);

        $this->assertSame($pricing, $result);
    }

    public function testUpdatePricingTier(): void
    {
        $pricingId = 1;
        $data = [
            'price' => 89.99,
            'discount_percentage' => 10
        ];

        $this->setTransactionExpectations();

        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();

        $this->pricingRepository->shouldReceive('update')
            ->with($pricingId, Mockery::on(function ($prepared) {
                return $prepared['price'] === 89.99
                    && $prepared['discount_percentage'] === 10;
            }))
            ->once()
            ->andReturn($pricing);

        $result = $this->service->updatePricingTier($pricingId, $data);

        $this->assertSame($pricing, $result);
    }

    public function testUpdatePricingTierThrowsWhenNotFound(): void
    {
        $pricingId = 1;
        $data = ['price' => 89.99];

        $this->setTransactionExpectations();

        $this->pricingRepository->shouldReceive('update')
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pricing tier not found');

        $this->service->updatePricingTier($pricingId, $data);
    }

    public function testDeletePricingTierWithTransaction(): void
    {
        $pricingId = 1;

        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->plan_id = 1;
        $pricing->is_default = false;

        $this->setTransactionExpectations();

        $this->pricingRepository->shouldReceive('find')
            ->with($pricingId)
            ->once()
            ->andReturn($pricing);

        // Mock the count check
        $this->pricingRepository->shouldReceive('getForPlan')
            ->with(1)
            ->once()
            ->andReturn(collect([$pricing, Mockery::mock(SubscriptionPlanPricing::class)]));

        $this->pricingRepository->shouldReceive('delete')
            ->with($pricingId)
            ->once()
            ->andReturn(true);

        $result = $this->service->deletePricingTier($pricingId);

        $this->assertTrue($result);
    }

    public function testDeletePricingTierThrowsWhenOnlyActive(): void
    {
        $pricingId = 1;

        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->plan_id = 1;

        $this->setTransactionExpectations();

        $this->pricingRepository->shouldReceive('find')
            ->with($pricingId)
            ->once()
            ->andReturn($pricing);

        $this->pricingRepository->shouldReceive('getForPlan')
            ->with(1)
            ->once()
            ->andReturn(collect([$pricing]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete the only active pricing tier');

        $this->service->deletePricingTier($pricingId);
    }

    public function testSetAsDefault(): void
    {
        $pricingId = 1;

        $this->pricingRepository->shouldReceive('setAsDefault')
            ->with($pricingId)
            ->once()
            ->andReturn(true);

        $result = $this->service->setAsDefault($pricingId);

        $this->assertTrue($result);
    }

    public function testToggleActive(): void
    {
        $pricingId = 1;

        $this->pricingRepository->shouldReceive('toggleActive')
            ->with($pricingId)
            ->once()
            ->andReturn(true);

        $result = $this->service->toggleActive($pricingId);

        $this->assertTrue($result);
    }

    public function testUpdateSortOrders(): void
    {
        $orderMap = [
            1 => 0,
            2 => 1,
            3 => 2
        ];

        $this->pricingRepository->shouldReceive('updateSortOrders')
            ->with($orderMap)
            ->once()
            ->andReturn(true);

        $result = $this->service->updateSortOrders($orderMap);

        $this->assertTrue($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseMock = Mockery::mock(Database::class);
        $this->pricingRepository = Mockery::mock(SubscriptionPlanPricingRepository::class);
        $this->service = new SubscriptionPlanPricingService($this->pricingRepository, $this->databaseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}