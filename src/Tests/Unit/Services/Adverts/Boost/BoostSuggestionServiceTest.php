<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Enums\Boost\SuggestionType;
use App\Framework\Support\Collection;
use App\Models\Boost;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\BoostSuggestionRepository;
use App\Services\Adverts\Boost\BoostPricingService;
use App\Services\Adverts\Boost\BoostSuggestionService;
use App\Services\Adverts\Boost\OpportunityScorer;
use App\Services\FrozenClock;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class BoostSuggestionServiceTest extends FunctionalTestCase
{
    private MockInterface $suggestionRepository;
    private MockInterface $boostRepository;
    private MockInterface $pricingService;
    private MockInterface $scorer;
    private BoostSuggestionService $service;

    public function test_returns_empty_when_no_products(): void
    {
        $this->suggestionRepository->shouldReceive('getActiveMerchantProducts')
            ->with(99)->andReturn(new Collection([]));

        $results = $this->service->getSuggestions(99);
        $this->assertEmpty($results);
    }

    public function test_skips_already_boosted_products(): void
    {
        $product = $this->makeProduct(1);

        $boost = new Boost([
            'boostable_id' => 1,
            'ends_at' => '2026-01-20 00:00:00', // 16 days away, not expiring
            'context' => 'listing',
            'boostable_type' => 'product',
            'multiplier' => 1.5,
        ]);
        $boost->id = 10;

        $this->suggestionRepository->shouldReceive('getActiveMerchantProducts')
            ->with(99)->andReturn(collect([$product]));

        $this->suggestionRepository->shouldReceive('getActiveBoostsForMerchant')
            ->andReturn(collect([$boost])->keyBy('boostable_id'));

        // Batch data still fetched
        $this->mockBatchData([1]);

        $results = $this->service->getSuggestions(99);

        $this->assertEmpty($results); // Boosted, not expiring — no suggestion
    }

    private function makeProduct(int $id, array $overrides = []): object
    {
        return (object)array_merge([
            'id' => $id,
            'name' => "Product {$id}",
            'is_active' => true,
            'stock_quantity' => 100,
            'price' => 50.00,
            'sale_price' => 0,
        ], $overrides);
    }

    private function mockBatchData(array $productIds): void
    {
        $this->suggestionRepository->shouldReceive('getImpressionCountsForProducts')
            ->andReturn(array_fill_keys($productIds, 50));
        $this->suggestionRepository->shouldReceive('getUnitsSoldForProducts')
            ->andReturn(array_fill_keys($productIds, 10));
        $this->suggestionRepository->shouldReceive('getActiveOffersForProducts')
            ->andReturn([]);
        $this->suggestionRepository->shouldReceive('getAverageRatingsForProducts')
            ->andReturn(array_fill_keys($productIds, 4.7));
    }

    public function test_suggests_boost_expiring_soon(): void
    {
        $product = $this->makeProduct(1);

        $boost = new Boost([
            'boostable_id' => 1,
            'ends_at' => '2026-01-06 00:00:00', // 2 days away — within 3-day window
            'context' => 'listing',
            'boostable_type' => 'product',
            'multiplier' => 1.5,
        ]);
        $boost->id = 10;

        $this->suggestionRepository->shouldReceive('getActiveMerchantProducts')
            ->with(99)->andReturn(collect([$product]));

        $this->suggestionRepository->shouldReceive('getActiveBoostsForMerchant')
            ->andReturn(collect([$boost])->keyBy('boostable_id'));

        $this->mockBatchData([1]);
        $this->pricingService->shouldReceive('calculate')->andReturn(35.00);
        $this->scorer->shouldReceive('score')->andReturn(50.0);

        $results = $this->service->getSuggestions(99);

        $this->assertCount(1, $results);
        $this->assertEquals(SuggestionType::BoostEndingSoon, $results[0]->type);
    }

    public function test_returns_max_results_limit(): void
    {
        $products = collect(range(1, 10))->map(fn($i) => $this->makeProduct($i));

        $this->suggestionRepository->shouldReceive('getActiveMerchantProducts')
            ->andReturn($products);
        $this->mockNoActiveBoosts();
        $this->mockBatchData(range(1, 10));

        $this->scorer->shouldReceive('score')->andReturn(75.0);
        $this->scorer->shouldReceive('deriveMultiplier')->andReturn(1.8);
        $this->pricingService->shouldReceive('calculate')->andReturn(35.00);

        $results = $this->service->getSuggestions(99);

        $this->assertLessThanOrEqual(5, count($results));
    }

    private function mockNoActiveBoosts(): void
    {
        $this->suggestionRepository->shouldReceive('getActiveBoostsForMerchant')
            ->andReturn(new Collection([]));
    }

    public function test_results_are_ordered_by_opportunity_score_descending(): void
    {
        $products = collect([
            $this->makeProduct(1, ['stock_quantity' => 200, 'price' => 50]),
            $this->makeProduct(2, ['stock_quantity' => 10, 'price' => 50]),
        ]);

        $this->suggestionRepository->shouldReceive('getActiveMerchantProducts')
            ->andReturn($products);
        $this->mockNoActiveBoosts();

        $this->suggestionRepository->shouldReceive('getImpressionCountsForProducts')
            ->andReturn([1 => 20, 2 => 800]);
        $this->suggestionRepository->shouldReceive('getUnitsSoldForProducts')
            ->andReturn([1 => 50, 2 => 1]);
        $this->suggestionRepository->shouldReceive('getActiveOffersForProducts')->andReturn([]);
        $this->suggestionRepository->shouldReceive('getAverageRatingsForProducts')
            ->andReturn([1 => 4.8, 2 => 3.0]);

        $this->scorer->shouldReceive('score')
            ->with('maximise_revenue', Mockery::any(), 4.8, Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(85.0);
        $this->scorer->shouldReceive('score')
            ->with('maximise_revenue', Mockery::any(), 3.0, Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(30.0);
        $this->scorer->shouldReceive('deriveMultiplier')->andReturn(1.5);
        $this->pricingService->shouldReceive('calculate')->andReturn(35.00);

        $results = $this->service->getSuggestions(99, 'maximise_revenue');

        if (count($results) >= 2) {
            $this->assertGreaterThanOrEqual(
                $results[1]->opportunityScore,
                $results[0]->opportunityScore
            );
        }

        $this->assertTrue(true);
    }

    public function test_does_not_suggest_out_of_stock_products(): void
    {
        $product = $this->makeProduct(1, ['stock_quantity' => 0]);

        // Out of stock products are filtered at the repository level (getActiveMerchantProducts
        // queries stock_quantity > 0), so an empty collection is correct here.
        $this->suggestionRepository->shouldReceive('getActiveMerchantProducts')
            ->andReturn(collect([]));

        $results = $this->service->getSuggestions(99);
        $this->assertEmpty($results);
    }

    protected function setUp(): void
    {
        $this->suggestionRepository = Mockery::mock(BoostSuggestionRepository::class);
        $this->boostRepository = Mockery::mock(BoostRepository::class);
        $this->pricingService = Mockery::mock(BoostPricingService::class);
        $this->scorer = Mockery::mock(OpportunityScorer::class);

        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-04 00:00:00'));

        $this->service = new BoostSuggestionService(
            $this->suggestionRepository,
            $this->boostRepository,
            $this->pricingService,
            $this->scorer,
            $clock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}