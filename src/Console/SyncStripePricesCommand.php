<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Framework\Support\Collection;
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

    public $description = 'Sync plan pricing rows to Stripe (standard + intro prices)';
    protected $signature = 'sync:stripe-prices {--dry-run} {--plan-id=} {--site-id=} {--intro-only}';

    public function __construct(
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly SubscriptionPlanRepository        $planRepository,
        private readonly StripePriceGateway                $stripePriceGateway,
    ) {}

    public function handle(): int
    {
        $result    = $this->createResult('sync:stripe-prices');
        $dryRun    = (bool) $this->option('dry-run');
        $introOnly = (bool) $this->option('intro-only');
        $planId    = $this->option('plan-id') ? (int) $this->option('plan-id') : null;
        $siteId    = $this->option('site-id') ? (int) $this->option('site-id') : null;

        $planIds = $this->resolvePlanIds($siteId);

        if ($planIds === []) {
            $this->info('No plans found for the given filters.');
            return self::SUCCESS;
        }

        $plansById = SubscriptionPlan::query()
            ->whereIn('id', $planIds)
            ->get()
            ->keyBy('id');

        if (!$introOnly) {
            $this->syncStandardPrices($result, $dryRun, $planId, $planIds, $plansById);
        }

        $this->syncIntroPrices($result, $dryRun, $planId, $planIds, $plansById);

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    // ── Standard price sync ──────────────────────────────────────────────────

    private function syncStandardPrices(
        mixed  $result,
        bool   $dryRun,
        ?int   $planIdFilter,
        array  $planIds,
        object $plansById,
    ): void {
        $query = SubscriptionPlanPricing::query()
            ->whereNull('stripe_price_id')
            ->where('is_active', true)
            ->whereIn('plan_id', $planIds);

        if ($planIdFilter !== null) {
            $query->where('plan_id', $planIdFilter);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('No unsynced standard pricing rows found.');
            return;
        }

        foreach ($rows as $pricing) {
            $this->syncStandardRow($pricing, $plansById, $result, $dryRun);
        }
    }

    private function syncStandardRow(
        SubscriptionPlanPricing $pricing,
        object                  $plansById,
        mixed                   $result,
        bool                    $dryRun,
    ): void {
        $plan  = $plansById->get($pricing->plan_id);
        $label = $this->rowLabel($pricing, $plan, 'standard');

        if (!$plan?->stripe_product_id) {
            $result->addMessage("Skipped {$label}: parent plan has no stripe_product_id.");
            return;
        }

        if ($dryRun) {
            $result->addMessage("[DRY-RUN] Would sync {$label}");
            $result->incrementSucceeded();
            return;
        }

        try {
            $stripePriceId = $this->stripePriceGateway->createRecurringPrice(
                $plan->stripe_product_id,
                (int) round($pricing->price * 100),
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
                result:    $result,
                message:   "Failed to sync {$label}: {$e->getMessage()}",
                context:   ['pricing_id' => $pricing->id],
                throwable: $e,
            );
        }
    }

    // ── Intro price sync ─────────────────────────────────────────────────────

    private function syncIntroPrices(
        mixed  $result,
        bool   $dryRun,
        ?int   $planIdFilter,
        array  $planIds,
        object $plansById,
    ): void {
        // Rows that have intro pricing configured but no Stripe intro price yet
        $query = SubscriptionPlanPricing::query()
            ->whereNotNull('intro_price')
            ->whereNotNull('intro_cycles')
            ->whereNull('stripe_intro_price_id')
            ->where('is_active', true)
            ->whereIn('plan_id', $planIds);

        if ($planIdFilter !== null) {
            $query->where('plan_id', $planIdFilter);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('No unsynced intro pricing rows found.');
            return;
        }

        foreach ($rows as $pricing) {
            $this->syncIntroRow($pricing, $plansById, $result, $dryRun);
        }
    }

    private function syncIntroRow(
        SubscriptionPlanPricing $pricing,
        object                  $plansById,
        mixed                   $result,
        bool                    $dryRun,
    ): void {
        $plan  = $plansById->get($pricing->plan_id);
        $label = $this->rowLabel($pricing, $plan, 'intro');

        if (!$plan?->stripe_product_id) {
            $result->addMessage("Skipped {$label}: parent plan has no stripe_product_id.");
            return;
        }

        // Intro price must also be less than the standard price — guard here
        // in case validation was bypassed (e.g. direct DB edit).
        if ((float) $pricing->intro_price >= (float) $pricing->price) {
            $result->addMessage("Skipped {$label}: intro_price is not less than standard price.");
            return;
        }

        if ($dryRun) {
            $result->addMessage("[DRY-RUN] Would sync intro price {$label}");
            $result->incrementSucceeded();
            return;
        }

        try {
            // Intro prices are still recurring — they're just a different
            // amount applied for a limited number of cycles via a schedule.
            $stripeIntroPriceId = $this->stripePriceGateway->createRecurringPrice(
                $plan->stripe_product_id,
                (int) round($pricing->intro_price * 100),
                $pricing->currency,
                $pricing->interval ?? 'month',
            );

            $this->pricingRepository->update($pricing->id, [
                'stripe_intro_price_id' => $stripeIntroPriceId,
            ]);

            $result->incrementSucceeded();
            $result->addMessage("Synced intro {$label} → {$stripeIntroPriceId}");

        } catch (\Throwable $e) {
            $this->reportFailure(
                result:    $result,
                message:   "Failed to sync intro price {$label}: {$e->getMessage()}",
                context:   ['pricing_id' => $pricing->id],
                throwable: $e,
            );
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Resolve the plan ID scope for both sync passes.
     * Returns an empty array if a site filter was supplied but has no plans.
     */
    public function resolvePlanIds(?int $siteId): array
    {
        if ($siteId === null) {
            return SubscriptionPlan::all()->pluck('id')->toArray();
        }

        return SubscriptionPlan::query()
            ->where('site_id', $siteId)
            ->get()
            ->pluck('id')
            ->toArray();
    }

    private function rowLabel(
        SubscriptionPlanPricing $pricing,
        ?SubscriptionPlan       $plan,
        string                  $type,
    ): string {
        return sprintf(
            '[Pricing %d | %s] plan "%s" | %s %s/%s',
            $pricing->id,
            $type,
            $plan?->name ?? "#{$pricing->plan_id}",
            $pricing->currency,
            $type === 'intro' ? $pricing->intro_price : $pricing->price,
            $pricing->interval ?? 'month',
        );
    }

    protected function queryStandardRows(array $planIds, ?int $planIdFilter): Collection
    {
        $query = SubscriptionPlanPricing::query()
            ->whereNull('stripe_price_id')
            ->where('is_active', true)
            ->whereIn('plan_id', $planIds);

        if ($planIdFilter !== null) {
            $query->where('plan_id', $planIdFilter);
        }

        return $query->get();
    }

    protected function queryIntroRows(array $planIds, ?int $planIdFilter): Collection
    {
        $query = SubscriptionPlanPricing::query()
            ->whereNotNull('intro_price')
            ->whereNotNull('intro_cycles')
            ->whereNull('stripe_intro_price_id')
            ->where('is_active', true)
            ->whereIn('plan_id', $planIds);

        if ($planIdFilter !== null) {
            $query->where('plan_id', $planIdFilter);
        }

        return $query->get();
    }
}