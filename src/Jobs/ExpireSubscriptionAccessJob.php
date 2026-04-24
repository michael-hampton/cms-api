<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\Queueable;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldBeUnique;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Services\Subscriptions\MemberAccessService;

/**
 * Enforces access expiry for cancelled and past-due subscriptions.
 *
 * This is the only place access is revoked. Webhooks and listeners
 * never revoke access directly — they record state and let this job
 * enforce it on schedule.
 *
 * Two cases handled:
 *
 *   CANCELLED — access is retained until current_period_end (the end of
 *   the last paid period). When that date passes, access is revoked.
 *
 *   PAST_DUE — access is retained during a grace window defined as
 *   current_period_end + GRACE_PERIOD_DAYS. When that window closes
 *   without a successful payment (which would have moved status back to
 *   ACTIVE via webhook), access is revoked.
 *
 * Register in Kernel.php:
 *   $schedule->job(ExpireSubscriptionAccessJob::class)->hourly()->withoutOverlapping();
 *
 * ShouldBeUnique prevents overlap if the job runs long.
 */
class ExpireSubscriptionAccessJob extends BaseJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Must match OnInvoicePaymentFailed::GRACE_PERIOD_DAYS.
     * Extract to a config value if you need it configurable per plan.
     */
    private const GRACE_PERIOD_DAYS = 7;
    public int $tries = 1;
    private MemberAccessService $accessService;
    private Logger $logger;

    public function handle(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->logger->info('ExpireSubscriptionAccessJob started', [
            'as_of' => $now->format('Y-m-d H:i:s'),
        ]);

        $revokedCount = 0;
        $errorCount = 0;

        // ── Case 1: CANCELLED subscriptions past their period end ──────────
        Subscription::where('status', SubscriptionStatus::CANCELLED->value)
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now->format('Y-m-d H:i:s'))
            ->chunkById(100, function ($subscriptions) use (
                $now, &$revokedCount, &$errorCount
            ) {
                foreach ($subscriptions as $subscription) {
                    if (!$this->hasActivePremiumAccess($subscription)) {
                        continue;
                    }

                    try {
                        $this->accessService->revokeSubscriptionAccess($subscription);
                        $revokedCount++;

                        $this->logger->info('ExpireSubscriptionAccessJob: access revoked (cancelled)', [
                            'subscription_id' => $subscription->id,
                            'end_date' => $subscription->end_date,
                        ]);
                    } catch (\Throwable $e) {
                        $errorCount++;
                        $this->logger->error('ExpireSubscriptionAccessJob: failed to revoke cancelled subscription', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // ── Case 2: PAST_DUE subscriptions past their grace window ─────────
        $graceExpiredBefore = $now->modify(sprintf('-%d days', self::GRACE_PERIOD_DAYS));

        Subscription::where('status', SubscriptionStatus::PAST_DUE->value)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $graceExpiredBefore->format('Y-m-d H:i:s'))
            ->chunkById(100, function ($subscriptions) use (
                &$revokedCount, &$errorCount
            ) {
                foreach ($subscriptions as $subscription) {
                    if (!$this->hasActivePremiumAccess($subscription)) {
                        continue;
                    }

                    try {
                        $this->accessService->revokeSubscriptionAccess($subscription);
                        $revokedCount++;

                        $this->logger->info('ExpireSubscriptionAccessJob: access revoked (past-due grace expired)', [
                            'subscription_id' => $subscription->id,
                            'current_period_end' => $subscription->current_period_end,
                        ]);
                    } catch (\Throwable $e) {
                        $errorCount++;
                        $this->logger->error('ExpireSubscriptionAccessJob: failed to revoke past-due subscription', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->logger->info('ExpireSubscriptionAccessJob completed', [
            'revoked' => $revokedCount,
            'errors' => $errorCount,
        ]);
    }

    /**
     * Cheap guard before calling the access service — skip subscriptions
     * that have already had access revoked so we don't generate pointless
     * log noise on every run.
     */
    private function hasActivePremiumAccess(Subscription $subscription): bool
    {
        $relation = $subscription->premiumAccess();

        $items = $relation instanceof \App\Framework\Support\Collection
            ? $relation
            : $relation->get();

        return $items->isNotEmpty();
    }

    public function uniqueId(): string
    {
        return 'expire-subscription-access';
    }

    public function uniqueFor(): int
    {
        return $this->timeout;
    }
}