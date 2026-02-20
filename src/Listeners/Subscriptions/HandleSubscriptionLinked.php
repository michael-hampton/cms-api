<?php

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionLinked;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Subscriptions\SubscriptionLinkedMail;
use App\Repositories\Members\MemberRepository;

/**
 * Handles all side effects after a print subscription is successfully linked:
 *   1. Grants digital access entitlements via the existing grantPremiumAccess mechanism
 *   2. Sends a confirmation email to the member
 *
 * This is the single listener required by the SubscriptionLinked event contract.
 * If either side effect fails it is logged and swallowed — linking itself already
 * succeeded and the member should not see an error for a downstream failure.
 */
class HandleSubscriptionLinked
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MailManager      $mailManager,
    )
    {
    }

    public function handle(SubscriptionLinked $event): void
    {
        $this->grantDigitalAccess($event);
        $this->sendConfirmationEmail($event);
    }

    // ── Private ───────────────────────────────────────────────────────

    private function grantDigitalAccess(SubscriptionLinked $event): void
    {
        try {
            $subscription = $event->subscription;

            // Grant digital/insider access — mirrors what SubscriptionRepository
            // does on createSubscription so the entitlement model stays consistent.
            $subscription->grantPremiumAccess('newsletter', 'insider');
            $subscription->update(['includes_digital_access' => true]);

            Logger::info('Digital access granted after subscription link', [
                'subscription_id' => $subscription->id,
                'member_id' => $event->memberId,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Failed to grant digital access after subscription link', [
                'subscription_id' => $event->subscription->id,
                'member_id' => $event->memberId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendConfirmationEmail(SubscriptionLinked $event): void
    {
        try {
            $member = $this->memberRepository->find($event->memberId);

            if (!$member || !$member->email) {
                Logger::warning('Cannot send subscription linked email — member not found', [
                    'member_id' => $event->memberId,
                ]);
                return;
            }

            $this->mailManager
                ->to($member->email)
                ->send(new SubscriptionLinkedMail($member, $event->subscription));

            Logger::info('Subscription linked confirmation email sent', [
                'member_id' => $event->memberId,
                'subscription_id' => $event->subscription->id,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Failed to send subscription linked confirmation email', [
                'member_id' => $event->memberId,
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}