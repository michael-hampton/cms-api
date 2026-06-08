<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Framework\Mail\MailManager;
use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\Queueable;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Mail\Subscriptions\PricingChangeNoticeMail;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPricingChange;

/**
 * Sends a single pricing change notice email to one subscriber.
 *
 * Dispatched once per active subscriber when a pricing change is scheduled.
 * Queued so that bulk sending does not block the request that creates the change.
 *
 * Failure is non-critical (notification email) — exceptions are caught and
 * logged rather than bubbled, so a single failed delivery does not block the
 * rest of the batch.
 */
class SendPricingChangeNoticeJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 60; // seconds

    private Member $member;
    private Subscription $subscription;
    private SubscriptionPricingChange $pricingChange;

    public function __construct()
    {
    }

    public function handle(): void
    {
        // Guard: skip if the change was cancelled while this job was queued
        if ($this->pricingChange->isCancelled()) {
            return;
        }

        // Guard: skip if the member no longer has an active subscription
        if (!in_array($this->subscription->status, \App\Models\Subscription::ACTIVE_STATUSES, true)) {
            return;
        }

        $mailManager = app(MailManager::class);

        try {
            $mailManager
                ->to($this->member->email ?? 'test')
                ->send(new PricingChangeNoticeMail(
                    $this->member,
                    $this->subscription,
                    $this->pricingChange,
                ));

        } catch (\Throwable $e) {
            // Non-critical: log and do not rethrow. The batch-completion logic
            // in NotifyAffectedSubscribersListener marks the change as notified
            // after all jobs are dispatched, not after all emails are confirmed
            // delivered — so a single bounce does not stall the status transition.
            Logger::warning('PricingChangeNoticeJob: failed to send notice email', [
                'member_id' => $this->member->id,
                'subscription_id' => $this->subscription->id,
                'pricing_change_id' => $this->pricingChange->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}