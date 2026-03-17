<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\StripePriceGateway;

class SyncStripePricesCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    public $description = 'Sync plan pricing rows without a stripe_price_id to Stripe';
    protected $signature = 'sync:stripe-prices {--dry-run} {--plan-id=} {--site-id=}';

    public function __construct(
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly SubscriptionPlanRepository        $planRepository,
        private readonly StripePriceGateway                $stripePriceGateway,
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('sync:stripe-prices');
        $dryRun = (bool)$this->option('dry-run');
        $planId = $this->option('plan-id') ? (int)$this->option('plan-id') : null;
        $siteId = $this->option('site-id') ? (int)$this->option('site-id') : null;

        $query = SubscriptionPlanPricing::query()
            ->whereNull('stripe_price_id')
            ->where('is_active', true);

        if ($planId !== null) {
            $query->where('plan_id', $planId);
        }

        if ($siteId !== null) {
            $planIds = SubscriptionPlan::query()
                ->where('site_id', $siteId)
                ->get()
                ->pluck('id')
                ->toArray();

            if (empty($planIds)) {
                $this->info('No plans found for the given site.');
                return self::SUCCESS;
            }
            $query->whereIn('plan_id', $planIds);
        }

        $pricingRows = $query->get();

        if ($pricingRows->isEmpty()) {
            $this->info('No unsynced pricing rows found.');
            return self::SUCCESS;
        }

        // Cache plans to avoid N+1
        $planIds = $pricingRows->pluck('plan_id')->unique()->values()->toArray();
        $plansById = SubscriptionPlan::query()
            ->whereIn('id', $planIds)
            ->get()
            ->keyBy('id');

        foreach ($pricingRows as $pricing) {
            $plan = $plansById->get($pricing->plan_id);
            $label = sprintf(
                '[Pricing %d] plan "%s" | %s %s/%s',
                $pricing->id,
                $plan?->name ?? "#{$pricing->plan_id}",
                $pricing->currency,
                $pricing->amount_cents,
                $pricing->interval ?? 'month',
            );

            if (!$plan || !$plan->stripe_product_id) {
                $result->addMessage("Skipped {$label}: parent plan has no stripe_product_id.");
                continue;
            }

            if ($dryRun) {
                $result->addMessage("[DRY-RUN] Would sync {$label}");
                $result->incrementSucceeded();
                continue;
            }

            try {
                $stripePriceId = $this->stripePriceGateway->createRecurringPrice(
                    $plan->stripe_product_id,
                    (int)round($pricing->price * 100),
                    $pricing->currency,
                    $pricing->interval ?? 'month',
                );

                $this->pricingRepository->update($pricing->id, ['stripe_price_id' => $stripePriceId]);

                if (!$plan->stripe_price_id && ($pricing->is_default ?? false)) {
                    $this->planRepository->update($plan->id, ['stripe_price_id' => $stripePriceId]);
                }

                $result->incrementSucceeded();
                $result->addMessage("Synced {$label} → {$stripePriceId}");
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to sync {$label}: {$e->getMessage()}",
                    context: ['pricing_id' => $pricing->id],
                    throwable: $e
                );
            }
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}