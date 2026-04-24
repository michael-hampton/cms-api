<?php

declare(strict_types=1);

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Models\Subscription;
use App\Services\Subscriptions\StripeSubscriptionReconciler;

/**
 * Reconciles local subscription state against Stripe.
 *
 * Stripe is the source of truth for all billing fields.
 * This command is the safety net for anything that slipped through webhooks.
 *
 * Schedule in Kernel.php:
 *   $schedule->command(ReconcileStripeSubscriptionsCommand::class)->dailyAt('03:00');
 *
 * Usage:
 *   php artisan billing:reconcile
 *   php artisan billing:reconcile --subscription_id=42    # single subscription
 *   php artisan billing:reconcile --dry-run               # log changes, write nothing
 */
class ReconcileStripeSubscriptionsCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;
    public $description = 'Reconciles local subscription billing state against Stripe.';
    protected $signature = 'billing:reconcile
                            {--subscription_id= : Reconcile a single subscription by local ID}
                            {--dry-run          : Log what would change without writing to the DB}';

    public function __construct(
        private readonly StripeSubscriptionReconciler $reconciler,
    )
    {

    }

    public function handle(): int
    {
        $isDryRun = (bool)$this->option('dry-run');
        $result = $this->createResult('billing:reconcile');

        if ($isDryRun) {
            $this->info('[dry-run] No changes will be written.');
        }

        $query = Subscription::whereNotNull('payment_subscription_id');

        if ($subscriptionId = $this->option('subscription_id')) {
            $query->where('id', (int)$subscriptionId);
        }

        $updated = 0;
        $skipped = 0;

        $query->chunkById(100, function ($subscriptions) use (
            $result, $isDryRun, &$updated, &$skipped
        ) {
            foreach ($subscriptions as $subscription) {
                try {
                    if ($isDryRun) {
                        $reconcileResult = $this->reconciler->reconcile($subscription);

                        if ($reconcileResult['action'] === 'updated') {
                            $this->info(sprintf(
                                '[dry-run] Would update subscription #%d (%s): %s',
                                $subscription->id,
                                $subscription->payment_subscription_id,
                                $this->formatChanges($reconcileResult['changes']),
                            ));
                        }

                        $result->incrementSucceeded();
                        continue;
                    }

                    $reconcileResult = $this->reconciler->reconcile($subscription);

                    match ($reconcileResult['action']) {
                        'updated' => ++$updated,
                        'skipped' => ++$skipped,
                        default => null,
                    };

                    if ($reconcileResult['action'] === 'updated') {
                        $result->addMessage(sprintf(
                            'Updated subscription #%d (%s): %s',
                            $subscription->id,
                            $subscription->payment_subscription_id,
                            $this->formatChanges($reconcileResult['changes']),
                        ));
                    }

                    $result->incrementSucceeded();

                } catch (\Throwable $e) {
                    $this->reportFailure(
                        result: $result,
                        message: "Failed to reconcile subscription #{$subscription->id}: {$e->getMessage()}",
                        context: [
                            'subscription_id' => $subscription->id,
                            'stripe_subscription_id' => $subscription->payment_subscription_id,
                        ],
                        throwable: $e,
                    );
                }
            }
        });

        $this->reportResult($result);

        if (!$isDryRun) {
            $this->info("Summary — updated: {$updated}, skipped: {$skipped}, failed: {$result->errors()}");
        }

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function formatChanges(array $changes): string
    {
        $parts = [];

        foreach ($changes as $field => $change) {
            $from = $this->formatValue($change['from']);
            $to = $this->formatValue($change['to']);
            $parts[] = "{$field}: {$from} → {$to}";
        }

        return implode(', ', $parts);
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string)$value;
    }
}