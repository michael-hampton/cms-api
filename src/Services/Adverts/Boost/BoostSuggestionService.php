<?php

namespace App\Services\Adverts\Boost;

use App\Contracts\ClockInterface;
use App\DTO\Boost\BoostSuggestionDTO;
use App\Enums\Boost\AutoBoostGoal;
use App\Enums\Boost\BoostContext;
use App\Enums\Boost\SuggestionType;
use App\Framework\Support\Config;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\BoostSuggestionRepository;

class BoostSuggestionService
{
    private array $cfg;

    public function __construct(
        private readonly BoostSuggestionRepository $suggestionRepository,
        private readonly BoostRepository           $boostRepository,
        private readonly BoostPricingService       $pricingService,
        private readonly OpportunityScorer         $scorer,
        private readonly ClockInterface $clock,
    )
    {
        $this->cfg = config('boost.suggestions');
    }

    /**
     * Returns top N suggestions for a merchant, ranked by opportunity score.
     * Goal influences scoring weights.
     *
     * @return BoostSuggestionDTO[]
     */
    public function getSuggestions(int $merchantId, string $goal = AutoBoostGoal::MaximiseRevenue->value): array
    {
        $products = $this->suggestionRepository->getActiveMerchantProducts($merchantId);

        if ($products->isEmpty()) {
            return [];
        }

        $productIds = $products->pluck('id')->toArray();

        // Fetch all analytics data in batch — never N+1
        $impressions = $this->suggestionRepository->getImpressionCountsForProducts($productIds, $this->cfg['analysis_window_days']);
        $unitsSold = $this->suggestionRepository->getUnitsSoldForProducts($productIds, $this->cfg['analysis_window_days']);
        $activeOffers = $this->suggestionRepository->getActiveOffersForProducts($productIds);
        $ratings = $this->suggestionRepository->getAverageRatingsForProducts($productIds);
        $activeBoosts = $this->suggestionRepository->getActiveBoostsForMerchant($merchantId, $productIds)
            ->keyBy('boostable_id');

        $suggestions = [];

        foreach ($products as $product) {
            // Never suggest already-boosted products
            if ($activeBoosts->has($product->id)) {
                $boost = $activeBoosts->get($product->id);
                $endsAt = $boost->ends_at;
                $now = $this->clock->now();
                $daysRemaining = (int)$now->diff($endsAt)->days;

                // If boost expiring soon — suggest renewal, then skip normal suggestions
                if ($daysRemaining <= $this->cfg['boost_expiry_warning_days'] && $daysRemaining >= 0) {
                    $suggestions[] = $this->buildExpiringBoostSuggestion($product, $boost, $daysRemaining, $goal);
                }

                continue;
            }

            $productImpressions = $impressions[$product->id] ?? 0;
            $productUnitsSold = $unitsSold[$product->id] ?? 0;
            $productRating = $ratings[$product->id] ?? 0.0;
            $offer = $activeOffers[$product->id] ?? null;
            $stockQuantity = (int)($product->stock_quantity ?? 0);

            // Derive discount % from offer or product prices
            $discountPercent = $this->deriveDiscountPercent($product, $offer);

            // Conversion rate = units sold / impressions (last 30d)
            $conversionRate = $productImpressions > 0
                ? round(($productUnitsSold / $productImpressions) * 100, 4)
                : 0.0;

            $opportunityScore = $this->scorer->score(
                goal: $goal,
                conversionRate: $conversionRate,
                averageRating: $productRating,
                discountPercent: $discountPercent,
                stockQuantity: $stockQuantity,
                impressionsLast30d: $productImpressions,
            );

            $suggestionType = $this->classifySuggestion(
                conversionRate: $conversionRate,
                impressions: $productImpressions,
                stockQuantity: $stockQuantity,
                rating: $productRating,
                discountPercent: $discountPercent,
                offer: $offer,
            );

            if ($suggestionType === null) {
                continue; // Product doesn't meet criteria for any suggestion type
            }

            $context = $this->suggestContext($goal, $suggestionType, $offer !== null);
            $multiplier = $this->scorer->deriveMultiplier($opportunityScore);
            $duration = Config::get("boost.auto_boost_durations.{$context}") ?? 7;
            $now = $this->clock->now();
            $endsAt = $now->modify("+{$duration} days");

            $estimatedCost = 0.0;
            try {
                $boostableType = $offer ? 'offer' : 'product';
                $estimatedCost = $this->pricingService->calculate($boostableType, $context, $now, $endsAt);
            } catch (\Exception) {
                // Non-critical — still show suggestion without cost
            }

            $suggestions[] = new BoostSuggestionDTO(
                productId: $product->id,
                productName: $product->name,
                boostableType: $offer ? 'offer' : 'product',
                offerId: $offer ? (int)$offer['id'] : null,
                type: $suggestionType,
                reason: $this->buildReason($suggestionType, $discountPercent, null),
                opportunityScore: $opportunityScore,
                suggestedContext: $context,
                suggestedMultiplier: $multiplier,
                estimatedCost: $estimatedCost,
                impressionsLast30d: $productImpressions,
                conversionRate: $conversionRate,
                stockQuantity: $stockQuantity,
                discountPercent: $discountPercent,
                averageRating: $productRating,
                daysUntilBoostExpiry: null,
            );
        }

        // Sort descending by opportunity score, return top N
        usort($suggestions, fn($a, $b) => $b->opportunityScore <=> $a->opportunityScore);

        return array_slice($suggestions, 0, $this->cfg['max_results']);
    }

    // ── Classification ───────────────────────────────────────────────────────

    private function buildExpiringBoostSuggestion(
        object $product,
        object $boost,
        int    $daysRemaining,
        string $goal,
    ): BoostSuggestionDTO
    {
        $context = $boost->context;
        $multiplier = $boost->multiplier;
        $now = $this->clock->now();
        $endsAt = $now->modify('+7 days');
        $boostableType = $boost->boostable_type;

        $estimatedCost = 0.0;
        try {
            $estimatedCost = $this->pricingService->calculate($boostableType, $context, $now, $endsAt);
        } catch (\Exception) {
        }

        $score = $this->scorer->score($goal, 0, 0, 0, 0, 0); // Renewal always shown regardless of score

        return new BoostSuggestionDTO(
            productId: $product->id,
            productName: $product->name,
            boostableType: $boostableType,
            offerId: null,
            type: SuggestionType::BoostEndingSoon,
            reason: $this->buildReason(SuggestionType::BoostEndingSoon, 0, $daysRemaining),
            opportunityScore: 100.0, // Always float to top
            suggestedContext: $context,
            suggestedMultiplier: $multiplier,
            estimatedCost: $estimatedCost,
            impressionsLast30d: 0,
            conversionRate: 0.0,
            stockQuantity: (int)($product->stock_quantity ?? 0),
            discountPercent: 0.0,
            averageRating: 0.0,
            daysUntilBoostExpiry: $daysRemaining,
        );
    }

    private function buildReason(SuggestionType $type, float $discountPercent, ?int $daysUntilExpiry): string
    {
        return match ($type) {
            SuggestionType::HighPotentialLowVisibility =>
            'This product converts well but has low visibility. Boosting could significantly increase sales.',
            SuggestionType::StrongDeal =>
            sprintf('This deal has a %.0f%% discount and is already selling. A boost could maximise revenue before it expires.', $discountPercent),
            SuggestionType::SlowMoverInventoryRisk =>
            'Inventory is moving slowly. A short boost could help clear stock before it ties up capital.',
            SuggestionType::TopRated =>
            'This product has strong customer feedback. Promoting it may amplify results.',
            SuggestionType::BoostEndingSoon =>
            sprintf('Your boost expires in %d day%s. Extending it will maintain your current visibility.', $daysUntilExpiry, $daysUntilExpiry === 1 ? '' : 's'),
        };
    }

    private function deriveDiscountPercent(object $product, mixed $offer): float
    {
        if ($offer && isset($offer['sale_price'], $offer['original_price']) && $offer['original_price'] > 0) {
            return round((($offer['original_price'] - $offer['sale_price']) / $offer['original_price']) * 100, 2);
        }

        if ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price) {
            return round((($product->price - $product->sale_price) / $product->price) * 100, 2);
        }

        return 0.0;
    }

    private function classifySuggestion(
        float $conversionRate,
        int   $impressions,
        int   $stockQuantity,
        float $rating,
        float $discountPercent,
        mixed $offer,
    ): ?SuggestionType
    {
        // High potential, low visibility — strongest signal
        if ($conversionRate >= $this->cfg['high_conversion_threshold']
            && $impressions < $this->cfg['low_impressions_threshold']
            && $stockQuantity >= $this->cfg['high_stock_threshold']) {
            return SuggestionType::HighPotentialLowVisibility;
        }

        // Strong deal — active offer, big discount, selling decently
        if ($offer !== null
            && $discountPercent >= $this->cfg['strong_deal_discount_min']
            && $conversionRate > 0) {
            return SuggestionType::StrongDeal;
        }

        // Top rated
        if ($rating >= $this->cfg['high_rating_threshold']
            && $stockQuantity >= $this->cfg['high_stock_threshold']) {
            return SuggestionType::TopRated;
        }

        // Slow mover with inventory risk
        if ($stockQuantity >= $this->cfg['high_stock_threshold'] && $conversionRate < 0.5) {
            $dailyVelocity = max(0.01, ($conversionRate / 100) * $impressions / 30);
            $daysOfStock = $stockQuantity / $dailyVelocity;

            if ($daysOfStock >= $this->cfg['slow_mover_days_threshold']) {
                return SuggestionType::SlowMoverInventoryRisk;
            }
        }

        return null; // Does not qualify for any suggestion type
    }

    private function suggestContext(string $goal, SuggestionType $type, bool $hasOffer): string
    {
        // Deals context preferred when there is an active offer or goal is to promote deals
        if ($hasOffer || $goal === AutoBoostGoal::PromoteDeals->value) {
            return BoostContext::Deals->value;
        }

        if ($goal === AutoBoostGoal::ClearInventory->value) {
            return BoostContext::Listing->value;
        }

        if ($type === SuggestionType::TopRated) {
            return BoostContext::Recommendations->value;
        }

        return BoostContext::Listing->value;
    }
}