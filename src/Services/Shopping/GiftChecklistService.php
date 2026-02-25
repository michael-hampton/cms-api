<?php

namespace App\Services\Shopping;

use App\DTO\Cart\GiftChecklistItem;
use App\Enums\CartItemType;
use App\Framework\Session\Session;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shopping\Factories\CartItemFactory;

/**
 * Manages free-gift checklist items in the cart.
 *
 * A "gift checklist item" is a zero-price cart row that flows through the
 * standard checkout pipeline and surfaces as an order line with is_gift=true
 * in the line metadata.  This provides a permanent historical record even
 * though cart rows are short-lived.
 *
 * Responsibilities:
 *   - Validate that the gift target (product or subscription plan) is active.
 *   - Prevent duplicate gift rows for the same checklist item.
 *   - Add / remove the zero-price cart row.
 *   - Provide a list of all gift items currently in the cart.
 *
 * This service deliberately does NOT contain eligibility logic (e.g. "has the
 * customer spent £X?").  Eligibility is the caller's responsibility.  This
 * service only manages the cart row lifecycle.
 *
 * Note on session/user resolution: CartService::getSessionId() is protected
 * and CartService::getUserId() is private, so this service resolves them
 * directly from the Session — the same source CartService uses — rather than
 * delegating through CartService's private API.
 */
class GiftChecklistService
{
    public function __construct(
        private readonly CartRepository             $cartRepository,
        private readonly CartItemFactory            $cartItemFactory,
        private readonly ProductRepository          $productRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Add a gift item to the cart.
     *
     * Returns ['success' => true] on success, or an error array with a human-
     * readable message that the caller can surface to the user.
     */
    public function addGift(GiftChecklistItem $gift): array
    {
        $sessionId = $this->resolveSessionId();
        $userId = $this->resolveUserId();

        // Guard: prevent duplicate gifts for the same checklist definition.
        if ($this->giftAlreadyInCart($gift, $userId, $sessionId)) {
            return ['success' => false, 'message' => 'This gift is already in your cart'];
        }

        if ($gift->isProduct()) {
            return $this->addProductGift($gift, $userId, $sessionId);
        }

        return $this->addSubscriptionGift($gift, $userId, $sessionId);
    }

    /**
     * Remove a gift item by its cart item ID.
     *
     * Only removes the item if it is actually a FREE_GIFT row belonging to the
     * current session / user.  Returns an error array if the item is not found
     * or does not belong to the session.
     */
    public function removeGift(int $cartItemId): array
    {
        $sessionId = $this->resolveSessionId();
        $userId = $this->resolveUserId();

        $cartItem = $this->cartRepository->findById($cartItemId, $userId, $sessionId);

        if (!$cartItem) {
            return ['success' => false, 'message' => 'Gift item not found'];
        }

        $options = is_string($cartItem->options)
            ? json_decode($cartItem->options, true)
            : (array)($cartItem->options ?? []);

        if (($options['type'] ?? '') !== CartItemType::FREE_GIFT->value) {
            return ['success' => false, 'message' => 'Item is not a gift'];
        }

        $this->cartRepository->delete($cartItemId);

        return ['success' => true, 'message' => 'Gift removed from cart'];
    }

    /**
     * Returns all FREE_GIFT cart rows for the current session / user, each
     * carrying the full metadata needed to reconstruct the gift on the order.
     */
    public function getGiftsInCart(): array
    {
        $sessionId = $this->resolveSessionId();
        $userId = $this->resolveUserId();

        $items = $this->cartRepository->findBySessionOrUser($userId, $sessionId);

        return $items->filter(function ($item) {
            $options = is_string($item->options)
                ? json_decode($item->options, true)
                : (array)($item->options ?? []);

            return ($options['type'] ?? '') === CartItemType::FREE_GIFT->value;
        })->values()->toArray();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function addProductGift(GiftChecklistItem $gift, ?int $userId, string $sessionId): array
    {
        $product = $this->productRepository->find($gift->productId);

        if (!$product || !$product->is_active) {
            return ['success' => false, 'message' => 'Gift product is not available'];
        }

        $cartItemData = $this->cartItemFactory->fromGiftProduct(
            sessionId: $sessionId,
            userId: $userId,
            product: $product,
            quantity: $gift->quantity,
            giftMetadata: $gift->toMetadata()
        );

        $this->cartRepository->create($cartItemData->toArray());

        return ['success' => true, 'message' => 'Gift added to cart'];
    }

    private function addSubscriptionGift(GiftChecklistItem $gift, ?int $userId, string $sessionId): array
    {
        $plan = $this->subscriptionPlanRepository->find($gift->subscriptionPlanId);

        if (!$plan) {
            return ['success' => false, 'message' => 'Gift subscription plan is not available'];
        }

        $cartItemData = $this->cartItemFactory->fromGiftSubscription(
            sessionId: $sessionId,
            userId: $userId,
            plan: $plan,
            quantity: $gift->quantity,
            giftMetadata: $gift->toMetadata()
        );

        $this->cartRepository->create($cartItemData->toArray());

        return ['success' => true, 'message' => 'Gift subscription added to cart'];
    }

    private function giftAlreadyInCart(GiftChecklistItem $gift, ?int $userId, string $sessionId): bool
    {
        $items = $this->cartRepository->findBySessionOrUser($userId, $sessionId);

        foreach ($items as $item) {
            $options = is_string($item->options)
                ? json_decode($item->options, true)
                : (array)($item->options ?? []);

            if (($options['type'] ?? '') !== CartItemType::FREE_GIFT->value) {
                continue;
            }

            if (
                $gift->isProduct() &&
                (int)($options['product_id'] ?? 0) === $gift->productId
            ) {
                return true;
            }

            if (
                $gift->isSubscription() &&
                (int)($options['subscription_plan_id'] ?? 0) === $gift->subscriptionPlanId
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mirrors CartService::getSessionId() — creates one if absent.
     * Duplicated here to avoid coupling to CartService's protected/private API.
     */
    private function resolveSessionId(): string
    {
        if (empty(Session::get('cart_session_id'))) {
            Session::put('cart_session_id', uniqid('cart_', true));
        }
        return Session::get('cart_session_id');
    }

    /**
     * Mirrors CartService::getUserId().
     */
    private function resolveUserId(): ?int
    {
        return Session::get('user_id');
    }
}