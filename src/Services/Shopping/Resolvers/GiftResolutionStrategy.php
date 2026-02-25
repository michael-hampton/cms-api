<?php

namespace App\Services\Shopping\Resolvers;


use App\DTO\Cart\GiftLine;
use App\DTO\Cart\PromotionCandidate;
use App\Enums\Gifts\GiftQuantityRule;

/**
 * Applies resolution rules to a set of eligible PromotionCandidates and
 * returns the final list of GiftLines to inject into the cart.
 *
 * Resolution rules (in order):
 *
 *   1. Group candidates by merchant scope (null = platform).
 *
 *   2. Within each merchant group:
 *      a. If any exclusive promotion is present, suppress all non-exclusive
 *         promotions in that group.
 *      b. Among surviving promotions, sort by priority descending.
 *
 *   3. Apply quantity rules per surviving promotion:
 *
 *      ONE_PER_QUALIFYING  → quantity = triggerCount, capped at maxPerOrder.
 *                            "I triggered it N times, I get N gifts (up to my cap)."
 *
 *      CAP                 → quantity = maxPerOrder, always.
 *                            Ignores how many times the promotion fired.
 *                            Use when you want a fixed ceiling regardless of cart behaviour.
 *
 *      MERGE               → contributes maxPerOrder directly to the shared pool,
 *                            ignoring triggerCount entirely.
 *                            When multiple MERGE promotions target the same gift,
 *                            their maxPerOrder values are SUMMED before the final
 *                            accumulated cap is applied.
 *                            Use for stackable campaign boosts:
 *                              Promo A → max 3 + Promo B → max 3 = pool of 6.
 *
 *   4. Merge promotions targeting the same gift into a single GiftLine.
 *      Quantities accumulate; the highest maxPerOrder seen acts as the final cap.
 *      Exception: MERGE promotions add to the pool uncapped until all MERGE
 *      contributions are summed, then the global accumulated cap is applied.
 *
 * Merchant scope isolation:
 *   Exclusive suppression never crosses merchant boundaries.
 *   Platform scope (merchantId = null) is isolated from all merchant scopes.
 */
class GiftResolutionStrategy
{
    /**
     * @param PromotionCandidate[] $candidates
     * @param array<string, string> $giftLabels gift key → human-readable name
     * @return GiftLine[]
     */
    public function resolve(array $candidates, array $giftLabels = []): array
    {
        if (empty($candidates)) {
            return [];
        }

        $groups = $this->groupByMerchant($candidates);
        $allGiftLines = [];

        foreach ($groups as $groupCandidates) {
            $surviving = $this->applyExclusivity($groupCandidates);

            usort($surviving, fn($a, $b) => $b->priority <=> $a->priority);

            foreach ($this->buildGiftLines($surviving, $giftLabels) as $line) {
                $allGiftLines[] = $line;
            }
        }

        return $allGiftLines;
    }

    // -------------------------------------------------------------------------
    // Grouping
    // -------------------------------------------------------------------------

    /**
     * @param PromotionCandidate[] $candidates
     * @return array<string, PromotionCandidate[]>
     */
    private function groupByMerchant(array $candidates): array
    {
        $groups = [];

        foreach ($candidates as $candidate) {
            $key = $candidate->merchantId !== null ? (string)$candidate->merchantId : 'platform';
            $groups[$key][] = $candidate;
        }

        return $groups;
    }

    // -------------------------------------------------------------------------
    // Exclusivity
    // -------------------------------------------------------------------------

    /**
     * If any exclusive promotion exists in the group, all non-exclusive
     * promotions in that group are suppressed.
     * Multiple exclusive promotions coexist — they don't suppress each other.
     *
     * @param PromotionCandidate[] $candidates
     * @return PromotionCandidate[]
     */
    private function applyExclusivity(array $candidates): array
    {
        $hasExclusive = array_any($candidates, fn($c) => $c->exclusive);

        if (!$hasExclusive) {
            return $candidates;
        }

        return array_values(array_filter($candidates, fn($c) => $c->exclusive));
    }

    // -------------------------------------------------------------------------
    // Quantity resolution and merging
    // -------------------------------------------------------------------------

    /**
     * Builds GiftLines from surviving candidates (already sorted priority desc).
     *
     * Accumulation map per gift key:
     *   [
     *     'regular_qty'       => int,   // from ONE_PER_QUALIFYING + CAP promos
     *     'merge_pool'        => int,   // from MERGE promos (additive max_per_order)
     *     'regular_cap'       => int,   // highest maxPerOrder among non-MERGE promos
     *     'sourcePromotionId' => int,
     *     'candidate'         => PromotionCandidate,
     *   ]
     *
     * Final quantity per gift key:
     *   regular portion → min(regular_qty, regular_cap)
     *   merge portion   → merge_pool (uncapped — MERGE is the cap)
     *   total           → regular + merge
     *
     * @param PromotionCandidate[] $candidates
     * @param array<string, string> $giftLabels
     * @return GiftLine[]
     */
    private function buildGiftLines(array $candidates, array $giftLabels): array
    {
        $accumulated = [];

        foreach ($candidates as $candidate) {
            $giftKey = $candidate->giftKey();

            if (!isset($accumulated[$giftKey])) {
                $accumulated[$giftKey] = [
                    'regular_qty' => 0,
                    'merge_pool' => 0,
                    'regular_cap' => 0,
                    'sourcePromotionId' => $candidate->promotionId,
                    'candidate' => $candidate,
                ];
            }

            if ($candidate->quantityRule === GiftQuantityRule::MERGE) {
                // MERGE contributes its maxPerOrder to an additive pool.
                // triggerCount is intentionally ignored — MERGE semantics are
                // "stack this campaign's allowance" not "count how many times triggered."
                $accumulated[$giftKey]['merge_pool'] += $candidate->maxPerOrder;
            } else {
                $qty = $this->resolveRegularQuantity($candidate);
                $accumulated[$giftKey]['regular_qty'] += $qty;
                $accumulated[$giftKey]['regular_cap'] = max(
                    $accumulated[$giftKey]['regular_cap'],
                    $candidate->maxPerOrder
                );
            }
        }

        $lines = [];

        foreach ($accumulated as $giftKey => $data) {
            $candidate = $data['candidate'];

            // Regular promotions are capped by the highest individual cap seen.
            $regularPortion = $data['regular_cap'] > 0
                ? min($data['regular_qty'], $data['regular_cap'])
                : $data['regular_qty'];

            // MERGE pool is not capped by regular_cap — each MERGE promo already
            // contributed its own cap. The sum IS the intended ceiling.
            $finalQuantity = $regularPortion + $data['merge_pool'];

            if ($finalQuantity <= 0) {
                continue;
            }

            $label = $giftLabels[$giftKey] ?? 'Free Gift';
            $lines[] = new GiftLine(
                giftType: $candidate->giftType,
                giftProductId: $candidate->giftProductId,
                giftSubscriptionPlanId: $candidate->giftSubscriptionPlanId,
                quantity: $finalQuantity,
                sourcePromotionId: $data['sourcePromotionId'],
                label: $label,
            );
        }

        return $lines;
    }

    /**
     * Resolves the contribution quantity for ONE_PER_QUALIFYING and CAP promotions.
     * MERGE is handled separately in buildGiftLines().
     */
    private function resolveRegularQuantity(PromotionCandidate $candidate): int
    {
        return match ($candidate->quantityRule) {
            GiftQuantityRule::ONE_PER_QUALIFYING => min(
                $candidate->triggerCount,
                $candidate->maxPerOrder
            ),
            GiftQuantityRule::CAP => $candidate->maxPerOrder,

            // Unreachable: MERGE is handled before this method is called.
            GiftQuantityRule::MERGE => 0,
        };
    }
}

// PHP 8.1 polyfill — array_any is native in PHP 8.2+
if (!function_exists('array_any')) {
    function array_any(array $array, callable $callback): bool
    {
        foreach ($array as $item) {
            if ($callback($item)) {
                return true;
            }
        }
        return false;
    }
}