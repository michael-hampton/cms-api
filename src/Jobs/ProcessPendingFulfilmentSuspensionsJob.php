<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\Queueable;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldBeUnique;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Services\Subscriptions\FulfilmentSuspensionService;

/**
 * Re-checks every subscription with a deferred fulfilment suspension
 * (fulfilment_suspension_pending = true — see FulfilmentSuspensionService)
 * and applies the suspension once its plan's FulfilmentSuspensionRule is
 * satisfied.
 *
 * Only plans with a 'days' or 'issues' delay override ever produce pending
 * rows — 'immediate' (the default) is applied synchronously by the
 * triggering listener and never reaches this table.
 *
 * Register in Kernel.php:
 *   $schedule->job(ProcessPendingFulfilmentSuspensionsJob::class)->hourly()->withoutOverlapping();
 */
class ProcessPendingFulfilmentSuspensionsJob extends BaseJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private FulfilmentSuspensionService $fulfilmentSuspensionService;
    private Logger $logger;

    public function handle(): void
    {
        $processed = 0;
        $suspended = 0;
        $errors = 0;

        Subscription::where('fulfilment_suspension_pending', true)
            ->chunkById(100, function ($subscriptions) use (&$processed, &$suspended, &$errors) {
                foreach ($subscriptions as $subscription) {
                    $processed++;

                    try {
                        if ($this->fulfilmentSuspensionService->reevaluatePending($subscription)) {
                            $suspended++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->logger->error('ProcessPendingFulfilmentSuspensionsJob: failed to re-evaluate subscription', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->logger->info('ProcessPendingFulfilmentSuspensionsJob completed', [
            'processed' => $processed,
            'suspended' => $suspended,
            'errors' => $errors,
        ]);
    }

    public function uniqueId(): string
    {
        return 'process-pending-fulfilment-suspensions';
    }

    public function uniqueFor(): int
    {
        return $this->timeout;
    }
}
