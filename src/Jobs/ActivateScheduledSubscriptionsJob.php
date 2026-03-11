<?php

namespace App\Jobs;

use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\Queueable;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldBeUnique;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Services\Subscriptions\SubscriptionActivationService;

/**
 * Activates all subscriptions that are in Scheduled status and whose
 * start_date is on or before the current time.
 *
 * Register in app/Console/Kernel.php:
 *
 *   $schedule->job(ActivateScheduledSubscriptionsJob::class)
 *            ->everyMinute()
 *            ->withoutOverlapping();
 *
 * ShouldBeUnique prevents a second instance from running while the first
 * is still processing. A single attempt is sufficient because any record
 * that fails is left in Scheduled status and will be retried on the next
 * scheduled run.
 */
class ActivateScheduledSubscriptionsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(
        SubscriptionActivationService $service,
        Logger                        $logger,
    ): void
    {
        $asOf = new \DateTimeImmutable();

        $logger->info('ActivateScheduledSubscriptionsJob started', [
            'as_of' => $asOf->format('Y-m-d H:i:s'),
        ]);

        $result = $service->activateScheduled($asOf);

        $logger->info('ActivateScheduledSubscriptionsJob completed', $result);
    }

    public function uniqueId(): string
    {
        return 'activate-scheduled-subscriptions';
    }

    public function uniqueFor(): int
    {
        return $this->timeout;
    }
}