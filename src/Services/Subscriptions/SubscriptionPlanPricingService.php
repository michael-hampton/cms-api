<?php

namespace App\Services\Subscriptions;

use App\Actions\Subscriptions\AddPlanPriceAction;
use App\Actions\Subscriptions\ReplacePlanPriceAction;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;

class SubscriptionPlanPricingService
{
    public function __construct(
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly Database               $database,
        private readonly AddPlanPriceAction     $addPlanPriceAction,
        private readonly ReplacePlanPriceAction $replacePlanPriceAction,
    )
    {
    }

    public function getPricingTiersForPlan(int $planId): Collection
    {
        return $this->pricingRepository->getForPlan($planId);
    }

    public function getDefaultPricingForPlan(int $planId): ?SubscriptionPlanPricing
    {
        return $this->pricingRepository->getDefaultForPlan($planId);
    }

    /**
     * Create a new pricing tier for a plan and create the corresponding Stripe Price.
     *
     * Delegates to AddPlanPriceAction which owns the Stripe integration and
     * domain invariant checks.
     *
     * amount_cents is derived here from $data['price'] so callers never pass it
     * directly — this prevents mismatches between the stored price and the Stripe amount.
     */
    public function createPricingTier(int $planId, array $data): SubscriptionPlanPricing
    {
        $this->validatePricingData($data);

        $data['amount_cents'] = $this->toAmountCents($data['price']);

        return $this->addPlanPriceAction->execute($planId, $data);
    }

    /**
     * Update a pricing tier by replacing it with a new versioned row.
     *
     * Prices are never modified in-place. Calling this method:
     *   - Deactivates the current pricing row.
     *   - Creates a new one with the supplied data merged over the existing values.
     *   - Creates a new Stripe Price object.
     *
     * Delegates to ReplacePlanPriceAction which owns the versioning logic.
     *
     * amount_cents is derived from $data['price'] — same rule as createPricingTier.
     */
    public function updatePricingTier(int $pricingId, array $data): SubscriptionPlanPricing
    {
        $this->validatePricingData($data);

        $data['amount_cents'] = $this->toAmountCents($data['price']);

        return $this->replacePlanPriceAction->execute($pricingId, $data);
    }

    /**
     * Derive amount_cents from a decimal price value.
     *
     * Stripe requires amounts as integers in the smallest currency unit.
     * Calculated here — not accepted from callers — to avoid floating-point
     * drift or mismatched values between price and amount_cents.
     */
    private function toAmountCents(float|int|string $price): int
    {
        return (int)round((float)$price * 100);
    }

    private function validatePricingData(array $data): void
    {
        if (!isset($data['duration_months']) || !is_numeric($data['duration_months'])) {
            throw new \InvalidArgumentException('Duration months is required and must be numeric');
        }
        if (!isset($data['issue_count']) || !is_numeric($data['issue_count'])) {
            throw new \InvalidArgumentException('Issue count is required and must be numeric');
        }
        if (!isset($data['price']) || !is_numeric($data['price'])) {
            throw new \InvalidArgumentException('Price is required and must be numeric');
        }
        if (empty($data['currency'])) {
            throw new \InvalidArgumentException('currency is required');
        }

        // Intro pricing cross-field rules
        // These mirror the request validation but defend against direct service calls.
        $introPrice  = $data['intro_price']  ?? null;
        $introCycles = $data['intro_cycles'] ?? null;

        if ($introPrice !== null) {
            if (!is_numeric($introPrice)) {
                throw new \InvalidArgumentException('intro_price must be numeric');
            }

            if ((float) $introPrice >= (float) $data['price']) {
                throw new \InvalidArgumentException(
                    'intro_price must be less than the standard price'
                );
            }

            if ($introCycles === null || !is_numeric($introCycles) || (int) $introCycles < 1) {
                throw new \InvalidArgumentException(
                    'intro_cycles is required and must be at least 1 when intro_price is set'
                );
            }
        }

        if ($introCycles !== null && $introPrice === null) {
            throw new \InvalidArgumentException(
                'intro_price is required when intro_cycles is set'
            );
        }

        if (isset($data['trial_days']) && $data['trial_days'] !== null) {
            $trialDays = $data['trial_days'];
            if (!is_numeric($trialDays) || (int) $trialDays < 1 || (int) $trialDays > 365) {
                throw new \InvalidArgumentException('trial_days must be between 1 and 365');
            }
        }
    }

    public function setAsDefault(int $pricingId): bool
    {
        return $this->pricingRepository->setAsDefault($pricingId);
    }

    public function deletePricingTier(int $pricingId): bool
    {
        return $this->database->transaction(function () use ($pricingId) {
            $pricing = $this->pricingRepository->find($pricingId);

            if (!$pricing) {
                throw new \Exception('Pricing tier not found');
            }

            $activeTiers = $this->pricingRepository->getForPlan($pricing->plan_id)->count();

            if ($activeTiers <= 1) {
                throw new \Exception('Cannot delete the only active pricing tier');
            }

            if ($pricing->is_default) {
                $newDefault = SubscriptionPlanPricing::where('plan_id', $pricing->plan_id)
                    ->where('id', '!=', $pricingId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->first();

                if ($newDefault) {
                    $this->pricingRepository->setAsDefault($newDefault->id);
                }
            }

            return $this->pricingRepository->delete($pricingId);
        });
    }

    public function toggleActive(int $pricingId): bool
    {
        return $this->pricingRepository->toggleActive($pricingId);
    }

    public function updateSortOrders(array $orderMap): bool
    {
        return $this->pricingRepository->updateSortOrders($orderMap);
    }
}