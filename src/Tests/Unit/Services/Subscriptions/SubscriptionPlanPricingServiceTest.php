<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Actions\Subscriptions\AddPlanPriceAction;
use App\Actions\Subscriptions\ReplacePlanPriceAction;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Services\Subscriptions\SubscriptionPlanPricingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class SubscriptionPlanPricingServiceTest extends FunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionPlanPricingRepository $pricingRepository;
    private Database $databaseMock;
    private AddPlanPriceAction $addPlanPriceAction;
    private ReplacePlanPriceAction $replacePlanPriceAction;
    private SubscriptionPlanPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingRepository = Mockery::mock(SubscriptionPlanPricingRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->addPlanPriceAction = Mockery::mock(AddPlanPriceAction::class);
        $this->replacePlanPriceAction = Mockery::mock(ReplacePlanPriceAction::class);

        $this->service = new SubscriptionPlanPricingService(
            $this->pricingRepository,
            $this->databaseMock,
            $this->addPlanPriceAction,
            $this->replacePlanPriceAction,
        );
    }

    // ---------------------------------------------------------------------------
    // getPricingTiersForPlan
    // ---------------------------------------------------------------------------

    public function testGetPricingTiersForPlan(): void
    {
        $collection = Mockery::mock(Collection::class);

        $this->pricingRepository
            ->shouldReceive('getForPlan')->with(5)->once()
            ->andReturn($collection);

        $this->assertSame($collection, $this->service->getPricingTiersForPlan(5));
    }

    // ---------------------------------------------------------------------------
    // getDefaultPricingForPlan
    // ---------------------------------------------------------------------------

    public function testGetDefaultPricingForPlan(): void
    {
        $pricing = $this->makePricing(3);

        $this->pricingRepository
            ->shouldReceive('getDefaultForPlan')->with(7)->once()
            ->andReturn($pricing);

        $this->assertSame($pricing, $this->service->getDefaultPricingForPlan(7));
    }

    public function testGetDefaultPricingForPlanReturnsNullWhenNoneSet(): void
    {
        $this->pricingRepository->shouldReceive('getDefaultForPlan')->once()->andReturn(null);

        $this->assertNull($this->service->getDefaultPricingForPlan(99));
    }

    // ---------------------------------------------------------------------------
    // createPricingTier — delegation and amount_cents derivation
    // ---------------------------------------------------------------------------

    public function testCreatePricingTierDelegatesToAddPlanPriceAction(): void
    {
        $pricing = $this->makePricing(1);

        $this->addPlanPriceAction
            ->shouldReceive('execute')->once()
            ->with(42, Mockery::on(fn($d) => $d['amount_cents'] === 999))
            ->andReturn($pricing);

        $result = $this->service->createPricingTier(42, $this->validPricingData());

        $this->assertSame($pricing, $result);
    }

    public function testCreatePricingTierDerivesAmountCentsFromPrice(): void
    {
        // 9.99 * 100 rounded = 999
        $pricing = $this->makePricing(1);

        $this->addPlanPriceAction
            ->shouldReceive('execute')->once()
            ->with(1, Mockery::on(fn($d) => $d['amount_cents'] === 999))
            ->andReturn($pricing);

        $this->service->createPricingTier(1, $this->validPricingData(['price' => 9.99]));
    }

    public function testCreatePricingTierWithTransaction(): void
    {
        // The action owns the transaction boundary. The service must pass
        // all data through without wrapping its own transaction.
        $pricing = $this->makePricing(10);

        $this->addPlanPriceAction
            ->shouldReceive('execute')->once()
            ->andReturn($pricing);

        // No transaction should be started at the service layer for create.
        $this->databaseMock->shouldReceive('transaction')->never();

        $result = $this->service->createPricingTier(1, $this->validPricingData());

        $this->assertSame($pricing, $result);
    }

    public function testCreatePricingTierSetsAsDefault(): void
    {
        // When is_default is passed, it must be forwarded to the action unchanged.
        $pricing = $this->makePricing(2);

        $this->addPlanPriceAction
            ->shouldReceive('execute')->once()
            ->with(1, Mockery::on(fn($d) => ($d['is_default'] ?? false) === true))
            ->andReturn($pricing);

        $this->service->createPricingTier(1, $this->validPricingData(['is_default' => true]));
    }

    // ---------------------------------------------------------------------------
    // createPricingTier — structural validation
    // ---------------------------------------------------------------------------

    public function testCreateRejectsMissingDurationMonths(): void
    {
        $this->addPlanPriceAction->shouldReceive('execute')->never();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration months');

        $this->service->createPricingTier(1, $this->validPricingData(['duration_months' => null]));
    }

    public function testCreateRejectsMissingIssueCount(): void
    {
        $this->addPlanPriceAction->shouldReceive('execute')->never();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Issue count');

        $this->service->createPricingTier(1, $this->validPricingData(['issue_count' => null]));
    }

    public function testCreateRejectsMissingPrice(): void
    {
        $this->addPlanPriceAction->shouldReceive('execute')->never();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price is required');

        $this->service->createPricingTier(1, $this->validPricingData(['price' => null]));
    }

    public function testCreateRejectsMissingCurrency(): void
    {
        $this->addPlanPriceAction->shouldReceive('execute')->never();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('currency');

        $this->service->createPricingTier(1, $this->validPricingData(['currency' => null]));
    }

    public function testCreateRejectsMissingInterval(): void
    {
        $this->addPlanPriceAction->shouldReceive('execute')->never();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('interval');

        $this->service->createPricingTier(1, $this->validPricingData(['interval' => null]));
    }

    // ---------------------------------------------------------------------------
    // updatePricingTier — delegation
    // ---------------------------------------------------------------------------

    public function testUpdatePricingTier(): void
    {
        $newPricing = $this->makePricing(2);

        $this->replacePlanPriceAction
            ->shouldReceive('execute')->once()
            ->with(10, Mockery::on(fn($d) => $d['amount_cents'] === 999))
            ->andReturn($newPricing);

        $result = $this->service->updatePricingTier(10, $this->validPricingData());

        $this->assertSame($newPricing, $result);
    }

    public function testUpdatePricingTierThrowsWhenNotFound(): void
    {
        // The action is responsible for the not-found check and throws RuntimeException.
        // The service must not swallow it.
        $this->replacePlanPriceAction
            ->shouldReceive('execute')->once()
            ->andThrow(new \RuntimeException('PlanPricing 999 not found.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PlanPricing 999 not found');

        $this->service->updatePricingTier(999, $this->validPricingData());
    }

    public function testUpdatePricingTierDerivesAmountCentsFromPrice(): void
    {
        $pricing = $this->makePricing(3);

        $this->replacePlanPriceAction
            ->shouldReceive('execute')->once()
            ->with(5, Mockery::on(fn($d) => $d['amount_cents'] === 1999))
            ->andReturn($pricing);

        $this->service->updatePricingTier(5, $this->validPricingData(['price' => 19.99]));
    }

    public function testUpdatePricingTierDoesNotCallAddAction(): void
    {
        $this->replacePlanPriceAction->shouldReceive('execute')->andReturn($this->makePricing(1));
        $this->addPlanPriceAction->shouldReceive('execute')->never();

        $this->service->updatePricingTier(1, $this->validPricingData());
    }

    // ---------------------------------------------------------------------------
    // updatePricingTier — structural validation
    // ---------------------------------------------------------------------------

    public function testUpdateRejectsMissingDurationMonths(): void
    {
        $this->replacePlanPriceAction->shouldReceive('execute')->never();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration months');

        $this->service->updatePricingTier(1, $this->validPricingData(['duration_months' => null]));
    }

    public function testUpdateRejectsMissingPrice(): void
    {
        $this->replacePlanPriceAction->shouldReceive('execute')->never();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price is required');

        $this->service->updatePricingTier(1, $this->validPricingData(['price' => null]));
    }

    public function testUpdateRejectsMissingCurrency(): void
    {
        $this->replacePlanPriceAction->shouldReceive('execute')->never();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('currency');

        $this->service->updatePricingTier(1, $this->validPricingData(['currency' => null]));
    }

    public function testUpdateRejectsMissingInterval(): void
    {
        $this->replacePlanPriceAction->shouldReceive('execute')->never();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('interval');

        $this->service->updatePricingTier(1, $this->validPricingData(['interval' => null]));
    }

    // ---------------------------------------------------------------------------
    // deletePricingTier
    // ---------------------------------------------------------------------------

    public function testDeletePricingTierRunsInsideTransaction(): void
    {
        $pricing = $this->makePricingObject(5, 1, false);
        $collection = Mockery::mock(Collection::class);
        $collection->shouldReceive('count')->andReturn(3);

        $this->databaseMock
            ->shouldReceive('transaction')->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->pricingRepository->shouldReceive('find')->with(5)->andReturn($pricing);
        $this->pricingRepository->shouldReceive('getForPlan')->with(1)->andReturn($collection);
        $this->pricingRepository->shouldReceive('delete')->with(5)->andReturn(true);

        $this->assertTrue($this->service->deletePricingTier(5));
    }

    public function testDeletePricingTierThrowsWhenNotFound(): void
    {
        $this->databaseMock
            ->shouldReceive('transaction')
            ->andReturnUsing(fn($cb) => $cb());

        $this->pricingRepository->shouldReceive('find')->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pricing tier not found');

        $this->service->deletePricingTier(999);
    }

    public function testDeletePricingTierThrowsWhenOnlyTierRemaining(): void
    {
        $pricing = $this->makePricingObject(1, 1, false);
        $collection = Mockery::mock(Collection::class);
        $collection->shouldReceive('count')->andReturn(1);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->pricingRepository->shouldReceive('find')->andReturn($pricing);
        $this->pricingRepository->shouldReceive('getForPlan')->andReturn($collection);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete the only active pricing tier');

        $this->service->deletePricingTier(1);
    }

    // ---------------------------------------------------------------------------
    // setAsDefault / toggleActive / updateSortOrders
    // ---------------------------------------------------------------------------

    public function testSetAsDefaultDelegatesToRepository(): void
    {
        $this->pricingRepository->shouldReceive('setAsDefault')->with(7)->once()->andReturn(true);

        $this->assertTrue($this->service->setAsDefault(7));
    }

    public function testToggleActiveDelegatesToRepository(): void
    {
        $this->pricingRepository->shouldReceive('toggleActive')->with(3)->once()->andReturn(true);

        $this->assertTrue($this->service->toggleActive(3));
    }

    public function testUpdateSortOrdersDelegatesToRepository(): void
    {
        $orderMap = [1 => 2, 2 => 1];

        $this->pricingRepository
            ->shouldReceive('updateSortOrders')->with($orderMap)->once()->andReturn(true);

        $this->assertTrue($this->service->updateSortOrders($orderMap));
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function validPricingData(array $overrides = []): array
    {
        $base = [
            'duration_months' => 1,
            'issue_count' => 12,
            'price' => 9.99,
            'currency' => 'gbp',
            'interval' => 'month',
        ];

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($base[$key]);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function makePricing(int $id): SubscriptionPlanPricing
    {
        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->id = $id;
        return $pricing;
    }

    /** Plain object for tests that need property reads (is_default, plan_id etc.) */
    private function makePricingObject(int $id, int $planId, bool $isDefault): object
    {
        $p = new SubscriptionPlanPricing();
        $p->id = $id;
        $p->plan_id = $planId;
        $p->is_default = $isDefault;
        return $p;
    }
}