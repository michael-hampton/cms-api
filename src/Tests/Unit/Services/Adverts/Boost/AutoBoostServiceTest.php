<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\DTO\Boost\AutoBoostPlanDTO;
use App\DTO\Boost\BoostAllocationDTO;
use App\Framework\Database\Database;
use App\Models\Boost;
use App\Models\MerchantAutoBoostSetting;
use App\Models\Product;
use App\Repositories\Adverts\Boost\MerchantAutoBoostSettingRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Adverts\Boost\AutoBoostService;
use App\Services\Adverts\Boost\BoostService;
use App\Services\Adverts\Boost\BoostSuggestionService;
use App\Services\Adverts\Boost\BudgetAllocator;
use App\Services\FrozenClock;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class AutoBoostServiceTest extends FunctionalTestCase
{
    private MockInterface $suggestionService;
    private MockInterface $budgetAllocator;
    private MockInterface $boostService;
    private MockInterface $settingRepository;
    private MockInterface $productRepository;
    private MockInterface $offerRepository;
    private MockInterface $databaseMock;
    private AutoBoostService $service;

    public function test_returns_empty_plan_when_settings_not_found(): void
    {
        $this->settingRepository->shouldReceive('findByMerchant')->with(99)->andReturn(null);

        $plan = $this->service->run(99);
        $this->assertEmpty($plan->allocations);
    }

    public function test_returns_empty_plan_when_auto_boost_disabled(): void
    {
        $setting = $this->makeSetting();
        $setting->is_enabled = false;
        $this->settingRepository->shouldReceive('findByMerchant')->andReturn($setting);

        $plan = $this->service->run(99);
        $this->assertEmpty($plan->allocations);
    }

    private function makeSetting(float $budget = 200.00, float $used = 0.0): MerchantAutoBoostSetting
    {
        $setting = new MerchantAutoBoostSetting([
            'merchant_id' => 99,
            'monthly_budget' => $budget,
            'goal' => 'maximise_revenue',
            'contexts_allowed' => ['listing'],
            'is_enabled' => true,
            'budget_used_this_month' => $used,
            'budget_period_month' => '2026-01',
        ]);
        $setting->id = (int)(microtime(true) * 1000);
        return $setting;
    }

    public function test_returns_empty_plan_when_budget_exhausted(): void
    {
        $this->settingRepository->shouldReceive('findByMerchant')
            ->andReturn($this->makeSetting(200.00, 200.00)); // all used

        $this->suggestionService
            ->shouldReceive('getSuggestions')
            ->andReturn([]); // or return mock suggestions

        $plan = $this->service->run(99);
        $this->assertEmpty($plan->allocations);
    }

    public function test_executes_plan_and_creates_boosts(): void
    {
        $setting = $this->makeSetting(200.00, 0.0);
        $this->settingRepository->shouldReceive('findByMerchant')->andReturn($setting);

        $this->suggestionService->shouldReceive('getSuggestions')
            ->once()
            ->with(99, 'maximise_revenue')
            ->andReturn([
                ['product_id' => 1, 'opportunity_score' => 80.0] // or whatever your allocator expects
            ]);

        $allocation = $this->makeAllocation(1, 35.00);
        $plan = $this->makePlan([$allocation]);

        $this->budgetAllocator->shouldReceive('allocate')->andReturn($plan);

        $product = new Product(['id' => 1, 'name' => 'Test Product', 'is_active' => true, 'stock_quantity' => 50]);
        $product->id = 1;
        $this->productRepository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->boostService->shouldReceive('createBoost')->once();
        $this->settingRepository->shouldReceive('incrementBudgetUsed')->with(99, 35.00)->once();

        $result = $this->service->run(99);

        $this->assertEquals(35.00, $result->totalAllocated);
    }

    private function makeAllocation(int $productId, float $cost): BoostAllocationDTO
    {
        return new BoostAllocationDTO(
            productId: $productId,
            productName: "Product {$productId}",
            boostableType: 'product',
            context: 'listing',
            multiplier: 1.5,
            cost: $cost,
            opportunityScore: 80.0,
            goal: 'maximise_revenue',
        );
    }

    private function makePlan(array $allocations): AutoBoostPlanDTO
    {
        $total = array_sum(array_map(fn($a) => $a->cost, $allocations));
        return new AutoBoostPlanDTO(99, 200.00, $total, 200.00 - $total, $allocations, false);
    }

    public function test_preview_does_not_call_boost_service(): void
    {
        $setting = $this->makeSetting(200.00, 0.0);
        $this->settingRepository->shouldReceive('findByMerchant')->andReturn($setting);
        $this->suggestionService->shouldReceive('getSuggestions')->andReturn([]);

        $plan = new AutoBoostPlanDTO(99, 200.00, 0, 200.00, [], true);
        $this->budgetAllocator->shouldReceive('allocate')->andReturn($plan);

        $this->boostService->shouldNotReceive('createBoost');

        $result = $this->service->preview(99);
        $this->assertTrue($result->isDryRun);
    }

    public function test_continues_when_single_allocation_fails(): void
    {
        $setting = $this->makeSetting(200.00, 0.0);
        $this->settingRepository->shouldReceive('findByMerchant')->andReturn($setting);
        $this->suggestionService->shouldReceive('getSuggestions')
            ->with(99, $setting->goal)
            ->andReturn([
                (object)[
                    'productId' => 1,
                    'context' => 'listing',
                    'opportunityScore' => 80,
                    'boostableType' => 'product',
                ],
                (object)[
                    'productId' => 2,
                    'context' => 'listing',
                    'opportunityScore' => 70,
                    'boostableType' => 'product',
                ]
            ]);

        $allocations = [
            $this->makeAllocation(1, 35.00),
            $this->makeAllocation(2, 35.00),
        ];
        $this->budgetAllocator->shouldReceive('allocate')
            ->andReturn($this->makePlan($allocations));

        $product1 = new Product(['id' => 1]);
        $product1->id = 1;
        $product2 = new Product(['id' => 2]);
        $product2->id = 2;

        $this->productRepository->shouldReceive('find')->with(1)->andReturn($product1);
        $this->productRepository->shouldReceive('find')->with(2)->andReturn($product2);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->boostService->shouldReceive('createBoost')
            ->with(Mockery::any(), Mockery::any(), 99, 'listing', Mockery::any(), Mockery::any(), Mockery::any())
            ->once()
            ->andThrow(new \RuntimeException('DB failure'))
            ->ordered();

        $successfulBoost = new Boost([
            'id' => uniqid(),
            'merchant_id' => 99,
            'product_id' => 2,
            'context' => 'listing',
            'multiplier' => 1.5,
        ]);

        $this->boostService->shouldReceive('createBoost')
            ->once()
            ->with(Mockery::any(), Mockery::any(), 99, 'listing', Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn($successfulBoost);

        $this->settingRepository->shouldReceive('incrementBudgetUsed')->once();

        $this->service->run(99); // Must not throw
        $this->assertTrue(true);
    }

    public function test_is_idempotent_when_no_new_suggestions(): void
    {
        $setting = $this->makeSetting(200.00, 0.0);
        $this->settingRepository->shouldReceive('findByMerchant')->andReturn($setting);
        $this->suggestionService->shouldReceive('getSuggestions')->andReturn([]);

        $emptyPlan = new AutoBoostPlanDTO(99, 200.00, 0, 200.00, [], false);
        $this->budgetAllocator->shouldReceive('allocate')->andReturn($emptyPlan);

        $this->boostService->shouldNotReceive('createBoost');

        $this->service->run(99);
        $this->service->run(99); // second run — same result, no duplicates

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        $this->suggestionService = Mockery::mock(BoostSuggestionService::class);
        $this->budgetAllocator = Mockery::mock(BudgetAllocator::class);
        $this->boostService = Mockery::mock(BoostService::class);
        $this->settingRepository = Mockery::mock(MerchantAutoBoostSettingRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->offerRepository = Mockery::mock(ProductOfferRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-04 00:00:00'));

        $this->service = new AutoBoostService(
            $this->suggestionService,
            $this->budgetAllocator,
            $this->boostService,
            $this->settingRepository,
            $this->productRepository,
            $this->offerRepository,
            $this->databaseMock,
            $clock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}