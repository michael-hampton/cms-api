<?php

namespace App\Services\PublicContent\Subscriptions;

use App\Models\SubscriptionPlan;

/**
 * Plan facade for the public-content subscription modal widget.
 * Precomputed price/delivery values avoid N+1 issue lookups during compose.
 */
final class PublicContentModalPlanViewModel
{
    /**
     * @param array{
     *   min: float|null,
     *   tier: mixed,
     *   delivery_type: string|null,
     *   available_format_count: int,
     *   is_out_of_stock: bool,
     *   show_from_prefix: bool
     * } $lowestEffectivePrice
     * @param list<string> $availableDeliveryOptions
     */
    public function __construct(
        private readonly SubscriptionPlan $plan,
        private readonly array $lowestEffectivePrice,
        private readonly array $availableDeliveryOptions,
    ) {
    }

    public function getLowestEffectivePrice(): array
    {
        return $this->lowestEffectivePrice;
    }

    public function getAvailableDeliveryOptions(): array
    {
        return $this->availableDeliveryOptions;
    }

    public function isOneTime(): bool
    {
        return $this->plan->isOneTime();
    }

    public function __get(string $key): mixed
    {
        return $this->plan->{$key};
    }

    public function __isset(string $key): bool
    {
        return isset($this->plan->{$key});
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->plan->{$method}(...$arguments);
    }
}
