<?php

namespace App\Services\Adverts;

use App\Models\Member;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Rewards\RewardsRepository;

class PromotionInjector
{
    public function __construct(
        private readonly ProductOfferRepository   $offerRepository,
        private readonly RewardsRepository        $rewardsRepository,
        private readonly ProductRepository        $productRepository,
        private readonly OfferVisibilityResolver  $offerResolver,
        private readonly RewardVisibilityResolver $rewardResolver,
        private readonly DealVisibilityResolver   $dealResolver
    )
    {
    }

    /**
     * Get blocks for injection based on surface and member
     *
     * @param string $surfaceType 'newsletter_issue' or 'page'
     * @param int $surfaceId
     * @param Member|null $member
     * @param int $siteId
     * @param string $channel 'newsletter' or 'web'
     * @return array
     */
    public function getBlocksForSurface(
        string  $surfaceType,
        int     $surfaceId,
        ?Member $member,
        int     $siteId,
        string  $channel
    ): array
    {
        $context = new RenderContext(
            $member?->id,
            $member?->subscription?->plan?->name,
            $member?->isPaid() ?? false,
            $channel,
            $surfaceType,
            $surfaceId,
            now_datetime()
        );

        // 1. Get eligible promotions by type (already sorted by priority)
        $offers = $this->getEligibleOffers($context, $channel);
        $rewards = $member
            ? $this->getEligibleRewards($member->id, $siteId, $context, $channel)
            : [];
        $deals = $this->getEligibleDeals($siteId, $context, $channel);

        // 2. Interleave all promotion types
        return $this->interleavePromotions($offers, $rewards, $deals);
    }

    private function getEligibleOffers(RenderContext $context, string $channel): array
    {
        $offers = $this->offerRepository->getActiveOffers();

        $blocks = [];
        $limit = $channel === 'newsletter' ? 3 : 5;

        foreach ($offers as $offer) {
            if (count($blocks) >= $limit) {
                break;
            }

            $decision = $this->offerResolver->resolve($offer, $context);

            if ($decision->shouldRender) {
                $blocks[] = [
                    'type' => 'offer',
                    'data' => [
                        'offer_id' => $offer->id,
                        'deal_id' => $decision->metadata['deal_id'] ?? null,
                    ],
                    'priority' => $this->calculateOfferPriority($offer),
                    'injection_type' => 'offer',
                ];
            }
        }

        // Sort by priority (highest first)
        usort($blocks, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $blocks;
    }

    private function calculateOfferPriority($offer): int
    {
        // Higher deal_value = higher priority
        $dealValue = $offer->original_price - $offer->sale_price;
        return (int)($dealValue * 100);
    }

    private function getEligibleRewards(
        int           $memberId,
        int           $siteId,
        RenderContext $context,
        string        $channel
    ): array
    {
        $decisions = $this->rewardResolver->resolveForMember($memberId, $siteId, $context);

        $blocks = [];
        $limit = $channel === 'newsletter' ? 2 : 3;

        // Sort by expiry date (nearest first)
        usort($decisions, function ($a, $b) {
            $expiryA = $a['reward']->expires_at ?? null;
            $expiryB = $b['reward']->expires_at ?? null;

            if (!$expiryA) return 1;
            if (!$expiryB) return -1;

            return $expiryA <=> $expiryB;
        });

        foreach (array_slice($decisions, 0, $limit) as $item) {
            $blocks[] = [
                'type' => 'reward',
                'data' => [
                    'reward_id' => $item['reward']->id,
                    'deal_id' => $item['decision']->metadata['deal_id'] ?? null,
                ],
                'priority' => $this->calculateRewardPriority($item['reward']),
                'injection_type' => 'reward',
            ];
        }

        return $blocks;
    }

    private function calculateRewardPriority($reward): int
    {
        // Nearest expiry = highest priority
        if (!$reward->expires_at) {
            return 0;
        }

        $daysUntilExpiry = now_datetime()->diffInDays($reward->expires_at, false);
        return max(0, 1000 - (int)$daysUntilExpiry);
    }

    private function getEligibleDeals(
        int           $siteId,
        RenderContext $context,
        string        $channel
    ): array
    {
        $products = $this->productRepository->getActiveSaleProducts($siteId);

        $blocks = [];
        $limit = $channel === 'newsletter' ? 2 : 3;

        // Sort by discount percentage (highest first)
        $products = $products->sortByDesc('discount_percentage');

        foreach ($products->take($limit * 2) as $product) {
            if (count($blocks) >= $limit) {
                break;
            }

            $decision = $this->dealResolver->resolve($product, $context);

            if ($decision->shouldRender) {
                $blocks[] = [
                    'type' => 'offer-deal',
                    'data' => [
                        'product_id' => $product->id,
                    ],
                    'priority' => $this->calculateDealPriority($product),
                    'injection_type' => 'deal',
                ];
            }
        }

        return $blocks;
    }

    private function calculateDealPriority($product): int
    {
        // Higher discount percentage = higher priority
        return (int)($product->discount_percentage * 10);
    }

    /**
     * Interleave promotions using round-robin while respecting:
     * - Priority within each type
     * - Max 2 consecutive blocks of same type
     * - Balanced distribution across all types
     */
    private function interleavePromotions(array $offers, array $rewards, array $deals): array
    {
        $result = [];
        $pools = [
            'offer' => $offers,
            'reward' => $rewards,
            'deal' => $deals,
        ];

        // Remove empty pools
        $pools = array_filter($pools, fn($pool) => !empty($pool));

        if (empty($pools)) {
            return [];
        }

        $lastType = null;
        $consecutiveCount = 0;

        // Continue until all pools are empty
        $maxIterations = array_sum(array_map('count', $pools)) * 3; // Safety limit
        $iterations = 0;

        // Continue until all pools are empty
        while (!empty($pools) && $iterations < $maxIterations) {
            $iterations++;

            // Get available pool types
            $availableTypes = array_keys($pools);

            if (empty($availableTypes)) {
                break;
            }

            // Find next type to use
            $chosenType = null;

            // Try to find a type that won't violate consecutive limit
            foreach ($availableTypes as $type) {
                if ($type !== $lastType || $consecutiveCount < 2) {
                    $chosenType = $type;
                    break;
                }
            }

            // If all types would violate limit, we must use a different type
            if ($chosenType === null) {
                // Find any type that isn't the last type
                foreach ($availableTypes as $type) {
                    if ($type !== $lastType) {
                        $chosenType = $type;
                        break;
                    }
                }
            }

            // If still no choice (only one type left), use it
            if ($chosenType === null) {
                $chosenType = $availableTypes[0];
            }

            // Take next block from chosen pool
            $block = array_shift($pools[$chosenType]);
            $result[] = $block;

            // Update consecutive tracking
            if ($chosenType === $lastType) {
                $consecutiveCount++;
            } else {
                $consecutiveCount = 1;
                $lastType = $chosenType;
            }

            // Remove pool if empty
            if (empty($pools[$chosenType])) {
                unset($pools[$chosenType]);
            }
        }

        return $result;
    }

    private function applyOrdering(array $blocks): array
    {
        // Sort by priority descending
        usort($blocks, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        // Apply interleaving: avoid more than 2 consecutive blocks of same type
        return $this->interleaveBlocks($blocks);
    }

    private function interleaveBlocks(array $blocks): array
    {
        $result = [];
        $lastType = null;
        $consecutiveCount = 0;
        $deferred = [];

        foreach ($blocks as $block) {
            $currentType = $block['injection_type'];

            if ($currentType === $lastType) {
                $consecutiveCount++;

                if ($consecutiveCount >= 2) {
                    // Defer this block
                    $deferred[] = $block;
                    continue;
                }
            } else {
                $consecutiveCount = 0;
            }

            $result[] = $block;
            $lastType = $currentType;
        }

        // Append deferred blocks at the end
        return array_merge($result, $deferred);
    }
}