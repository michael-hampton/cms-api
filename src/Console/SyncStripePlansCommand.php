<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\StripeProductGateway;

class SyncStripePlansCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    public $description = 'Sync subscription plans without a stripe_product_id to Stripe';
    protected $signature = 'sync:stripe-plans {--dry-run} {--site-id=}';

    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly StripeProductGateway       $gateway
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('sync:stripe-plans');
        $dryRun = (bool)$this->option('dry-run');
        $siteId = $this->option('site-id') ? (int)$this->option('site-id') : null;

        $query = SubscriptionPlan::whereNull('stripe_product_id');
        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        $plans = $query->get();

        if ($plans->isEmpty()) {
            $this->info('No unsynced plans found.');
            return self::SUCCESS;
        }

        foreach ($plans as $plan) {
            $label = "[Plan #{$plan->id}] \"{$plan->name}\"";

            if ($dryRun) {
                $result->addMessage("[DRY-RUN] Would sync {$label}");
                $result->incrementSucceeded();
                continue;
            }

            try {
                $stripeProductId = $this->gateway->createProduct($plan->name);
                $this->planRepository->update($plan->id, ['stripe_product_id' => $stripeProductId]);

                $result->incrementSucceeded();
                $result->addMessage("Synced {$label} → {$stripeProductId}");
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to sync {$label}: {$e->getMessage()}",
                    context: ['plan_id' => $plan->id],
                    throwable: $e
                );
            }
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}