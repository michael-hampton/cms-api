<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\DTO\Boost\BoostSuggestionDTO;
use App\Enums\Boost\SuggestionType;
use App\Services\Adverts\Boost\BudgetAllocator;
use PHPUnit\Framework\TestCase;

class BudgetAllocatorTest extends TestCase
{
    private BudgetAllocator $allocator;

    public function test_allocates_within_budget(): void
    {
        $suggestions = [
            $this->makeSuggestion(1, 35.00, 90),
            $this->makeSuggestion(2, 35.00, 80),
            $this->makeSuggestion(3, 35.00, 70),
        ];

        $plan = $this->allocator->allocate(99, 80.00, $suggestions, 'maximise_revenue');

        $this->assertLessThanOrEqual(80.00, $plan->totalAllocated);
        $this->assertCount(2, $plan->allocations);
        $this->assertEquals(10.00, $plan->remaining);
    }

    private function makeSuggestion(int $productId, float $cost, float $score): BoostSuggestionDTO
    {
        return new BoostSuggestionDTO(
            productId: $productId,
            productName: "Product {$productId}",
            boostableType: 'product',
            offerId: null,
            type: SuggestionType::HighPotentialLowVisibility,
            reason: 'Test reason',
            opportunityScore: $score,
            suggestedContext: 'listing',
            suggestedMultiplier: 1.5,
            estimatedCost: $cost,
            impressionsLast30d: 100,
            conversionRate: 3.0,
            stockQuantity: 50,
            discountPercent: 0.0,
            averageRating: 4.5,
            daysUntilBoostExpiry: null,
        );
    }

    public function test_does_nothing_when_budget_is_zero(): void
    {
        $suggestions = [$this->makeSuggestion(1, 35.00, 90)];

        $plan = $this->allocator->allocate(99, 0, $suggestions, 'maximise_revenue');

        $this->assertEmpty($plan->allocations);
        $this->assertEquals(0, $plan->totalAllocated);
    }

    public function test_skips_suggestion_too_expensive_but_allocates_cheaper_one(): void
    {
        $suggestions = [
            $this->makeSuggestion(1, 200.00, 90), // too expensive
            $this->makeSuggestion(2, 30.00, 80), // fits
        ];

        $plan = $this->allocator->allocate(99, 50.00, $suggestions, 'maximise_revenue');

        $this->assertCount(1, $plan->allocations);
        $this->assertEquals(2, $plan->allocations[0]->productId);
    }

    public function test_dry_run_flag_is_preserved(): void
    {
        $plan = $this->allocator->allocate(99, 100.00, [], 'maximise_revenue', dryRun: true);
        $this->assertTrue($plan->isDryRun);
    }

    public function test_total_budget_never_exceeded(): void
    {
        $suggestions = array_map(
            fn($i) => $this->makeSuggestion($i, 35.00, 90 - $i),
            range(1, 20)
        );

        $plan = $this->allocator->allocate(99, 100.00, $suggestions, 'maximise_revenue');

        $this->assertLessThanOrEqual(100.00, $plan->totalAllocated);
        $this->assertEquals(
            round($plan->totalBudget - $plan->totalAllocated, 2),
            $plan->remaining
        );
    }

    public function test_empty_suggestions_returns_empty_plan(): void
    {
        $plan = $this->allocator->allocate(99, 100.00, [], 'maximise_revenue');

        $this->assertEmpty($plan->allocations);
        $this->assertEquals(0.0, $plan->totalAllocated);
        $this->assertEquals(100.00, $plan->remaining);
    }

    protected function setUp(): void
    {
        $this->allocator = new BudgetAllocator();
    }
}