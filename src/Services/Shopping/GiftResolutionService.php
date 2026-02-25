<?php

namespace App\Services\Shopping;

use App\DTO\Cart\CartContext;
use App\DTO\Cart\CartLineItem;
use App\DTO\Cart\GiftLine;
use App\DTO\Cart\PromotionCandidate;
use App\Enums\CartItemType;
use App\Enums\Gifts\GiftType;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shopping\Resolvers\GiftEligibilityCollector;
use App\Services\Shopping\Resolvers\GiftResolutionStrategy;

/**
 * Orchestrates the full gift resolution pipeline for a cart.
 *
 * Pipeline:
 *   1. Build CartContext from the current cart state.
 *   2. GiftEligibilityCollector → PromotionCandidate[]
 *   3. GiftResolutionStrategy   → GiftLine[]
 *   4. Fetch gift labels (product/plan names) from DB.
 *   5. Sync cart: remove stale gifts, update changed quantities, add new gifts.
 *
 * Sync strategy (step 5):
 *   - Gift key present in cart but not in desired → remove.
 *   - Gift key present in both, quantity differs   → update quantity.
 *   - Gift key desired but not in cart             → add.
 *
 * This means if a customer removes a qualifying product, the gift disappears
 * automatically on the next sync. If they add more of a qualifying product,
 * the gift quantity updates automatically.
 *
 * Call sites:
 *   CartService::addItem() and removeItem() should call resolveAndSync()
 *   after every cart mutation.
 *
 * Architectural note — merchantId coupling:
 *   CartContext currently accepts a single merchantId. This is fine for
 *   merchant-scoped or platform carts. For true multi-merchant carts where
 *   items from different merchants coexist, the caller should pass null and
 *   the per-item merchantId on CartLineItem will be used by the resolver
 *   for grouping. The repository query in findCandidatesForCart will need
 *   to receive all unique merchantIds from line items in that case.
 *   See GiftPromotionRepository::findCandidatesForCart() for the query.
 */
class GiftResolutionService
{
    public function __construct(
        private readonly GiftEligibilityCollector   $collector,
        private readonly GiftResolutionStrategy     $strategy,
        private readonly GiftChecklistService       $giftChecklistService,
        private readonly CartRepository             $cartRepository,
        private readonly ProductRepository          $productRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
    )
    {
    }

    /**
     * Resolve eligible gifts and sync FREE_GIFT cart items.
     *
     * @param string $sessionId
     * @param int|null $userId
     * @param int|null $merchantId Pass null for platform/multi-merchant carts.
     * @param bool $isFirstOrder
     * @return array{added: GiftLine[], updated: array<int, int>, removed: int[]}
     */
    public function resolveAndSync(
        string $sessionId,
        ?int   $userId,
        ?int   $merchantId = null,
        bool   $isFirstOrder = false,
    ): array
    {
        $cartContext = $this->buildCartContext($sessionId, $userId, $merchantId, $isFirstOrder);
        $candidates = $this->collector->collect($cartContext);
        $giftLabels = $this->fetchGiftLabels($candidates);
        $giftLines = $this->strategy->resolve($candidates, $giftLabels);

        return $this->syncCartGifts($giftLines, $sessionId, $userId);
    }

    /**
     * Returns resolved GiftLines without mutating the cart.
     * Useful for "you qualify for these gifts" previews.
     *
     * @return GiftLine[]
     */
    public function preview(
        string $sessionId,
        ?int   $userId,
        ?int   $merchantId = null,
        bool   $isFirstOrder = false,
    ): array
    {
        $cartContext = $this->buildCartContext($sessionId, $userId, $merchantId, $isFirstOrder);
        $candidates = $this->collector->collect($cartContext);
        $giftLabels = $this->fetchGiftLabels($candidates);

        return $this->strategy->resolve($candidates, $giftLabels);
    }

    // -------------------------------------------------------------------------
    // Cart context
    // -------------------------------------------------------------------------

    private function buildCartContext(
        string $sessionId,
        ?int   $userId,
        ?int   $merchantId,
        bool   $isFirstOrder,
    ): CartContext
    {
        $rawItems = $this->cartRepository->findBySessionOrUser($userId, $sessionId);
        $lineItems = [];
        $cartTotal = 0.0;
        $itemCount = 0;

        foreach ($rawItems as $item) {
            $options = is_string($item->options)
                ? json_decode($item->options, true)
                : (array)($item->options ?? []);

            $isGift = ($options['type'] ?? '') === CartItemType::FREE_GIFT->value;
            $itemType = CartItemType::tryFrom($options['type'] ?? '') ?? CartItemType::PRODUCT;

            $lineItem = new CartLineItem(
                cartItemId: (int)$item->id,
                type: $itemType,
                productId: $item->product_id ? (int)$item->product_id : null,
                subscriptionPlanId: $item->subscription_plan_id ? (int)$item->subscription_plan_id : null,
                price: (float)$item->price,
                quantity: (int)$item->quantity,
                isGift: $isGift,
                merchantId: $item->merchant_id ? (int)$item->merchant_id : null,
                categoryIds: $this->resolveCategoryIds($item),
            );

            $lineItems[] = $lineItem;

            if (!$isGift) {
                $cartTotal += $lineItem->price * $lineItem->quantity;
                $itemCount += $lineItem->quantity;
            }
        }

        return new CartContext(
            lineItems: $lineItems,
            cartTotal: $cartTotal,
            itemCount: $itemCount,
            isFirstOrder: $isFirstOrder,
            userId: $userId,
            merchantId: $merchantId,
        );
    }

    private function resolveCategoryIds(object $cartItem): array
    {
        if (!isset($cartItem->category_ids) || $cartItem->category_ids === null) {
            return [];
        }

        return array_map('intval', explode(',', (string)$cartItem->category_ids));
    }

    // -------------------------------------------------------------------------
    // Label hydration
    // -------------------------------------------------------------------------

    /**
     * @param PromotionCandidate[] $candidates
     * @return array<string, string>  gift key → label
     */
    private function fetchGiftLabels(array $candidates): array
    {
        $productIds = [];
        $planIds = [];

        foreach ($candidates as $candidate) {
            if ($candidate->giftType === GiftType::PRODUCT && $candidate->giftProductId) {
                $productIds[] = $candidate->giftProductId;
            }
            if ($candidate->giftType === GiftType::SUBSCRIPTION && $candidate->giftSubscriptionPlanId) {
                $planIds[] = $candidate->giftSubscriptionPlanId;
            }
        }

        $labels = [];

        if (!empty($productIds)) {
            foreach ($this->productRepository->findMany(array_unique($productIds)) as $product) {
                $labels["product:{$product->id}"] = $product->name;
            }
        }

        if (!empty($planIds)) {
            foreach ($this->subscriptionPlanRepository->findMany(array_unique($planIds)) as $plan) {
                $labels["subscription:{$plan->id}"] = $plan->name;
            }
        }

        return $labels;
    }

    // -------------------------------------------------------------------------
    // Cart sync
    // -------------------------------------------------------------------------

    /**
     * Three-way sync:
     *   - Stale gifts (in cart, not desired)       → remove
     *   - Quantity mismatch (in both, qty differs) → update
     *   - New gifts (desired, not in cart)         → add
     *
     * @param GiftLine[] $desiredLines
     * @return array{added: GiftLine[], updated: array<int, int>, removed: int[]}
     */
    private function syncCartGifts(array $desiredLines, string $sessionId, ?int $userId): array
    {
        $currentGifts = $this->giftChecklistService->getGiftsInCart();

        // Build current map: gift key → {cartItemId, quantity}
        $currentMap = [];
        foreach ($currentGifts as $cartItem) {
            $options = is_string($cartItem->options)
                ? json_decode($cartItem->options, true)
                : (array)($cartItem->options ?? []);

            $key = $this->cartItemGiftKey($options);
            if ($key !== null) {
                $currentMap[$key] = [
                    'id' => (int)$cartItem->id,
                    'quantity' => (int)$cartItem->quantity,
                ];
            }
        }

        // Build desired map: gift key → GiftLine
        $desiredMap = [];
        foreach ($desiredLines as $line) {
            $desiredMap[$this->giftLineKey($line)] = $line;
        }

        $removed = [];
        $updated = [];
        $added = [];

        // Remove stale gifts
        foreach ($currentMap as $key => $current) {
            if (!isset($desiredMap[$key])) {
                $this->giftChecklistService->removeGift($current['id']);
                $removed[] = $current['id'];
            }
        }

        foreach ($desiredMap as $key => $desiredLine) {
            if (!isset($currentMap[$key])) {
                // Add new gift
                $this->giftChecklistService->addGift($desiredLine->toGiftChecklistItem());
                $added[] = $desiredLine;
            } elseif ($currentMap[$key]['quantity'] !== $desiredLine->quantity) {
                // Update quantity if it has changed
                $cartItemId = $currentMap[$key]['id'];
                $this->cartRepository->updateQuantity($cartItemId, $desiredLine->quantity);
                $updated[$cartItemId] = $desiredLine->quantity;
            }
        }

        return [
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
        ];
    }

    private function giftLineKey(GiftLine $line): string
    {
        return match ($line->giftType) {
            GiftType::PRODUCT => "product:{$line->giftProductId}",
            GiftType::SUBSCRIPTION => "subscription:{$line->giftSubscriptionPlanId}",
        };
    }

    private function cartItemGiftKey(array $options): ?string
    {
        if (!empty($options['product_id'])) {
            return "product:{$options['product_id']}";
        }
        if (!empty($options['subscription_plan_id'])) {
            return "subscription:{$options['subscription_plan_id']}";
        }
        return null;
    }
}