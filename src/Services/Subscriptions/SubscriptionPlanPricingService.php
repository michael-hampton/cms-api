<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;

class SubscriptionPlanPricingService
{
    public function __construct(
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly Database                          $database
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

    public function createPricingTier(array $data): SubscriptionPlanPricing
    {
        $this->validatePricingData($data);

        return $this->database->transaction(function () use ($data) {
            $pricing = $this->pricingRepository->create($data);

            if ($data['is_default'] ?? false) {
                $this->pricingRepository->setAsDefault($pricing->id);
            }

            return $pricing;
        });
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
    }

    public function setAsDefault(int $pricingId): bool
    {
        return $this->pricingRepository->setAsDefault($pricingId);
    }

    public function updatePricingTier(int $pricingId, array $data): ?SubscriptionPlanPricing
    {
        return $this->database->transaction(function () use ($pricingId, $data) {

            $pricing = $this->pricingRepository->update($pricingId, $data);

            if (!$pricing) {
                throw new \Exception('Pricing tier not found');
            }

            if ($data['is_default'] ?? false) {
                $this->pricingRepository->setAsDefault($pricingId);
            }

            return $pricing;
        });
    }

    public function deletePricingTier(int $pricingId): bool
    {
        return $this->database->transaction(function () use ($pricingId) {
            $pricing = $this->pricingRepository->find($pricingId);

            if (!$pricing) {
                throw new \Exception('Pricing tier not found');
            }

            // Can't delete if it's the only active tier
            $activeTiers = $this->pricingRepository->getForPlan($pricing->plan_id)->count();

            if ($activeTiers <= 1) {
                throw new \Exception('Cannot delete the only active pricing tier');
            }

            // If this was default, set another as default
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