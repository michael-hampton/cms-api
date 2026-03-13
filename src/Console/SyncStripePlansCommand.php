#!/usr/bin/env php
<?php

/**
 * Sync subscription plans that have no stripe_product_id to Stripe.
 *
 * Usage:
 *   php artisan sync:stripe-plans [--dry-run] [--site-id=<id>]
 *
 * Options:
 *   --dry-run    Print what would be synced without writing anything
 *   --site-id    Restrict sync to a single site
 */

namespace App\Console;

use App\Actions\Subscriptions\CreatePlanAction;
use App\Framework\Console\Command;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;

class SyncStripePlansCommand extends Command
{
    public $description = 'Sync subscription plans without a stripe_product_id to Stripe';
    protected $signature = 'sync:stripe-plans {--dry-run} {--site-id=}';

    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly CreatePlanAction           $createPlanAction,
    )
    {
    }

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $siteId = $this->option('site-id') ? (int)$this->option('site-id') : null;

        $this->info('Fetching unsynced plans…');

        $query = SubscriptionPlan::whereNull('stripe_product_id');

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        $plans = $query->get();

        if ($plans->isEmpty()) {
            $this->info('No unsynced plans found. Nothing to do.');
            return 0;
        }

        $this->info(sprintf('Found %d plan(s) without a stripe_product_id.', $plans->count()));

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($plans as $plan) {
            $label = sprintf('[Plan %d] "%s" (site %d)', $plan->id, $plan->name, $plan->site_id);

            if ($dryRun) {
                echo "  [DRY-RUN] Would sync {$label}";
                $synced++;
                continue;
            }

            try {
                // CreatePlanAction expects the plan to NOT yet exist, so we call the
                // gateway directly here rather than re-creating the DB row.
                // We use the action's injected gateway via a dedicated sync method.
                $this->syncPlan($plan);
                echo "  ✔ Synced {$label}";
                $synced++;
            } catch (\Throwable $e) {
                $this->error("  ✘ Failed {$label}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info(sprintf(
            'Done. Synced: %d | Failed: %d | Skipped: %d',
            $synced, $failed, $skipped,
        ));

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Create a Stripe product for an already-persisted plan and store the ID.
     */
    private function syncPlan(mixed $plan): void
    {
        // Re-uses the action's dependencies by calling the action with a stub
        // that skips the DB insert step — we only need the Stripe product created.
        // The cleanest way: resolve the gateway and repository directly.
        /** @var \App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface $gateway */
        $gateway = app(\App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface::class);

        $stripeProductId = $gateway->createProduct($plan->name);

        $this->planRepository->update($plan->id, ['stripe_product_id' => $stripeProductId]);
    }
}