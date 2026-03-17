<?php

namespace App\Services\Adverts\Boost;

use App\DTO\Boost\AutoBoostPlanDTO;
use App\Framework\Database\Database;
use App\Framework\Support\Config;
use App\Framework\Support\Logger;
use App\Repositories\Adverts\Boost\MerchantAutoBoostSettingRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\SystemClock;

class AutoBoostService
{
    public function __construct(
        private readonly BoostSuggestionService             $suggestionService,
        private readonly BudgetAllocator                    $budgetAllocator,
        private readonly BoostService                       $boostService,
        private readonly MerchantAutoBoostSettingRepository $settingRepository,
        private readonly ProductRepository                  $productRepository,
        private readonly ProductOfferRepository             $offerRepository,
        private readonly Database                           $database,
        private readonly SystemClock $clock,
    )
    {
    }

    /**
     * Dry run: returns the plan without executing any boosts.
     * Use this in the UI to preview what Auto Boost would do.
     */
    public function preview(int $merchantId): AutoBoostPlanDTO
    {
        return $this->buildPlan($merchantId, dryRun: true);
    }

    private function buildPlan(int $merchantId, bool $dryRun): AutoBoostPlanDTO
    {
        $setting = $this->settingRepository->findByMerchant($merchantId);

        if (!$setting || !$setting->is_enabled) {
            return new AutoBoostPlanDTO($merchantId, 0, 0, 0, [], $dryRun);
        }

        // Reset budget counter if we've entered a new calendar month
        $setting->resetIfNewPeriod();

        $availableBudget = $setting->remainingBudget();

        if ($availableBudget <= 0) {
            Logger::info('AutoBoost skipped — budget exhausted', ['merchant_id' => $merchantId]);
            return new AutoBoostPlanDTO($merchantId, $setting->monthly_budget, $setting->monthly_budget, 0, [], $dryRun);
        }

        $goal = $setting->goal;
        $suggestions = $this->suggestionService->getSuggestions($merchantId, $goal);

        if (empty($suggestions)) {
            return new AutoBoostPlanDTO($merchantId, $setting->monthly_budget, 0, $availableBudget, [], $dryRun);
        }

        $plan = $this->budgetAllocator->allocate($merchantId, $availableBudget, $suggestions, $goal, $dryRun);

        if (!$dryRun && !empty($plan->allocations)) {
            $this->executePlan($plan, $setting, $merchantId);
        }


        return $plan;
    }

    private function executePlan(AutoBoostPlanDTO $plan, object $setting, int $merchantId): void
    {
        foreach ($plan->allocations as $allocation) {
            try {
                $this->database->transaction(function () use ($allocation, $merchantId) {
                    $boostableType = $allocation->boostableType;
                    $now = $this->clock->now();
                    $duration = Config::get("boost.auto_boost_durations.{$allocation->context}", 7);
                    $endsAt = $now->modify("+{$duration} days");

                    $target = $boostableType === 'offer'
                        ? $this->offerRepository->find($allocation->productId)
                        : $this->productRepository->find($allocation->productId);

                    if (!$target) {
                        throw new \RuntimeException("Target not found for allocation: product #{$allocation->productId}");
                    }

                    $this->boostService->createBoost(
                        target: $target,
                        boostableType: $boostableType,
                        merchantId: $merchantId,
                        context: $allocation->context,
                        startsAt: $now,
                        endsAt: $endsAt,
                        multiplier: $allocation->multiplier,
                    );
                });

                // Track budget spend outside transaction — increment is non-critical
                $this->settingRepository->incrementBudgetUsed($merchantId, $allocation->cost);

            } catch (\Exception $e) {
                // Non-critical per allocation — log and continue to next
                Logger::error('AutoBoost failed to create boost for allocation', [
                    'merchant_id' => $merchantId,
                    'product_id' => $allocation->productId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Called by AutoBoostJob. Runs for all merchants with Auto Boost enabled.
     * Catches per-merchant errors — one failure must not stop others.
     */
    public function runForAll(): void
    {
        $settings = $this->settingRepository->getEnabledSettings();

        foreach ($settings as $setting) {
            try {
                $this->run($setting->merchant_id);
            } catch (\Exception $e) {
                Logger::error('AutoBoost failed for merchant', [
                    'merchant_id' => $setting->merchant_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Execute Auto Boost for a single merchant.
     * Idempotent — will not create duplicate boosts for already-boosted products.
     */
    public function run(int $merchantId): AutoBoostPlanDTO
    {
        return $this->buildPlan($merchantId, dryRun: false);
    }
}