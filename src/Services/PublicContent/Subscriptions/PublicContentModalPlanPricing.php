<?php

namespace App\Services\PublicContent\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;

/**
 * Public-content-only modal pricing. Uses a prefetched next issue so compose
 * does not re-enter SubscriptionPlan::getNextIssue() per pricing tier.
 */
class PublicContentModalPlanPricing
{
    public function lowestEffectivePrice(SubscriptionPlan $plan, ?IssueDelivery $nextIssue): array
    {
        $tiers = $plan->pricingTiers;
        $defaultTier = $tiers->first(static fn(SubscriptionPlanPricing $tier): bool => (bool) $tier->is_default);

        if ($defaultTier instanceof SubscriptionPlanPricing) {
            $fromDefault = $this->fromTiers($plan, [$defaultTier], $nextIssue);
            if ($fromDefault['tier'] !== null) {
                return $fromDefault;
            }
        }

        $fromAll = $this->fromTiers($plan, $tiers, $nextIssue);
        if ($fromAll['tier'] !== null || $tiers->isNotEmpty()) {
            return $fromAll;
        }

        return [
            'min' => (float) $plan->price,
            'tier' => null,
            'delivery_type' => null,
            'available_format_count' => 0,
            'is_out_of_stock' => false,
            'show_from_prefix' => false,
        ];
    }

    public function availableDeliveryOptions(SubscriptionPlan $plan, ?IssueDelivery $nextIssue): array
    {
        if (!$plan->isOneTime()) {
            return $plan->getDeliveryOptions();
        }

        $options = [];

        if ($this->isDigitalInStock($plan, $nextIssue)) {
            $options[] = SubscriptionType::DIGITAL->value;
        }

        if ($this->isPrintInStock($plan, $nextIssue)) {
            $options[] = SubscriptionType::PRINTED->value;
        }

        return $options;
    }

    /**
     * @param iterable<SubscriptionPlanPricing> $tiers
     * @return array{
     *   min: float|null,
     *   tier: SubscriptionPlanPricing|null,
     *   delivery_type: string|null,
     *   available_format_count: int,
     *   is_out_of_stock: bool,
     *   show_from_prefix: bool
     * }
     */
    private function fromTiers(SubscriptionPlan $plan, iterable $tiers, ?IssueDelivery $nextIssue): array
    {
        $best = null;
        $availableFormats = [];

        foreach ($tiers as $tier) {
            if (!$tier instanceof SubscriptionPlanPricing) {
                continue;
            }

            foreach ($this->candidates($plan, $tier, $nextIssue) as $candidate) {
                $availableFormats[$candidate['delivery_type']] = true;

                if ($best === null || $candidate['price'] < $best['price']) {
                    $best = $candidate;
                }
            }
        }

        if ($best === null) {
            return [
                'min' => null,
                'tier' => null,
                'delivery_type' => null,
                'available_format_count' => 0,
                'is_out_of_stock' => true,
                'show_from_prefix' => false,
            ];
        }

        $availableFormatCount = count($availableFormats);

        return [
            'min' => $best['price'],
            'tier' => $best['tier'],
            'delivery_type' => $best['delivery_type'],
            'available_format_count' => $availableFormatCount,
            'is_out_of_stock' => false,
            'show_from_prefix' => $availableFormatCount > 1,
        ];
    }

    /**
     * @return list<array{delivery_type: string, price: float, tier: SubscriptionPlanPricing}>
     */
    private function candidates(
        SubscriptionPlan $plan,
        SubscriptionPlanPricing $tier,
        ?IssueDelivery $nextIssue,
    ): array {
        $candidates = [];
        $digitalAvailable = $plan->isOneTime()
            ? $this->isDigitalInStock($plan, $nextIssue)
            : $plan->hasDigitalOption();
        $printAvailable = $plan->isOneTime()
            ? $this->isPrintInStock($plan, $nextIssue)
            : $plan->hasPrintOption();

        if ($digitalAvailable) {
            $candidates[] = [
                'delivery_type' => SubscriptionType::DIGITAL->value,
                'price' => $tier->getEffectiveDigitalPrice(),
                'tier' => $tier,
            ];
        }

        if ($printAvailable) {
            $candidates[] = [
                'delivery_type' => SubscriptionType::PRINTED->value,
                'price' => $tier->getEffectivePrintPrice(),
                'tier' => $tier,
            ];
        }

        return $candidates;
    }

    private function isPrintInStock(SubscriptionPlan $plan, ?IssueDelivery $nextIssue): bool
    {
        if (!$plan->hasPrintOption()) {
            return false;
        }

        return $nextIssue !== null && $nextIssue->isInStock();
    }

    private function isDigitalInStock(SubscriptionPlan $plan, ?IssueDelivery $nextIssue): bool
    {
        if (!$plan->hasDigitalOption()) {
            return false;
        }

        foreach (['digital_stock_quantity', 'digital_inventory_quantity'] as $field) {
            if (isset($plan->{$field}) && is_numeric($plan->{$field})) {
                return (int) $plan->{$field} > 0;
            }
        }

        foreach (['digital_in_stock', 'is_digital_in_stock'] as $field) {
            if (isset($plan->{$field})) {
                return (bool) $plan->{$field};
            }
        }

        $metadata = is_array($nextIssue?->metadata ?? null) ? $nextIssue->metadata : [];

        foreach (['digital_stock_quantity', 'digital_inventory_quantity'] as $key) {
            if (array_key_exists($key, $metadata) && is_numeric($metadata[$key])) {
                return (int) $metadata[$key] > 0;
            }
        }

        foreach (['digital_in_stock', 'is_digital_in_stock'] as $key) {
            if (array_key_exists($key, $metadata)) {
                return (bool) $metadata[$key];
            }
        }

        if ($plan->release_date && $plan->release_date > now_datetime()) {
            return (bool) $plan->pre_release_enabled;
        }

        return true;
    }
}
