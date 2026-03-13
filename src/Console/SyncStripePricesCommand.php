#!/usr/bin/env php
<?php

/**
 * Sync plan pricing rows that have no stripe_price_id to Stripe.
 *
 * Prerequisites:
 *   - The parent plan must already have a stripe_product_id.
 *     Run sync:stripe-plans first if needed.
 *
 * Usage:
 *   php artisan sync:stripe-prices [--dry-run] [--plan-id=<id>] [--site-id=<id>]
 *
 * Options:
 *   --dry-run    Print what would be synced without writing anything
 *   --plan-id    Restrict sync to a single plan
 *   --site-id    Restrict sync to a single site (joins through plans)
 */

namespace App\Console;

use App\Framework\Console\Command;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;

class SyncStripePricesCommand extends Command
{
    public $description = 'Sync plan pricing rows without a stripe_price_id to Stripe';
    protected $signature = 'sync:stripe-prices {--dry-run} {--plan-id=} {--site-id=}';

    public function __construct(
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly SubscriptionPlanRepository        $planRepository,
        private readonly StripePriceGatewayInterface       $stripePriceGateway,
    )
    {
    }

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $planId = $this->option('plan-id') ? (int)$this->option('plan-id') : null;
        $siteId = $this->option('site-id') ? (int)$this->option('site-id') : null;

        $this->info('Fetching unsynced pricing rows…');

        $query = SubscriptionPlanPricing::query()
            ->whereNull('stripe_price_id')
            ->where('is_active', true);

        if ($planId !== null) {
            $query->where('plan_id', $planId);
        }

        if ($siteId !== null) {
            // Join through the plans table to filter by site
            $planIds = SubscriptionPlan::query()
                ->where('site_id', $siteId)
                ->get()
                ->pluck('id')
                ->toArray();

            if (empty($planIds)) {
                $this->info('No plans found for the given site.');
                return 0;
            }

            $query->whereIn('plan_id', $planIds);
        }

        $pricingRows = $query->get();

        if ($pricingRows->isEmpty()) {
            $this->info('No unsynced pricing rows found. Nothing to do.');
            return 0;
        }

        $this->info(sprintf('Found %d pricing row(s) without a stripe_price_id.', $pricingRows->count()));

        // Cache plans to avoid N+1
        $planIds = $pricingRows->pluck('plan_id')->unique()->values()->toArray();
        $plansById = SubscriptionPlan::query()
            ->whereIn('id', $planIds)
            ->get()
            ->keyBy('id');

        $synced = 0;
        $failed = 0;

        foreach ($pricingRows as $pricing) {
            $plan = $plansById->get($pricing->plan_id) ?? null;
            $label = sprintf(
                '[Pricing %d] plan "%s" | %s %s/%s',
                $pricing->id,
                $plan?->name ?? "#{$pricing->plan_id}",
                $pricing->currency,
                $pricing->amount_cents,
                $pricing->interval ?? 'month',
            );

            if (!$plan || !$plan->stripe_product_id) {
                $this->warn("  ⚠ Skipped {$label}: parent plan has no stripe_product_id. Run sync:stripe-plans first.");
                continue;
            }

            if ($dryRun) {
                echo "  [DRY-RUN] Would sync {$label}";
                $synced++;
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

                // Backfill the plan's default stripe_price_id if not already set
                if (!$plan->stripe_price_id && ($pricing->is_default ?? false)) {
                    $this->planRepository->update($plan->id, ['stripe_price_id' => $stripePriceId]);
                }

                echo "  ✔ Synced {$label} → {$stripePriceId}";
                $synced++;
            } catch (\Throwable $e) {
                $this->error("  ✘ Failed {$label}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info(sprintf('Done. Synced: %d | Failed: %d', $synced, $failed));

        return $failed > 0 ? 1 : 0;
    }
}