<?php

declare(strict_types=1);

namespace App\Notifications\Subscriptions;

use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Subscriptions\PaymentFailedMailable;
use App\Mail\Subscriptions\PaymentReceivedMailable;
use App\Mail\Subscriptions\SubscriptionCancelledMailable;
use App\Models\Subscription;

/**
 * Sends subscription notification emails via the framework's MailManager.
 *
 * This is the concrete implementation of SubscriptionNotificationDispatcher.
 * Listeners depend on the interface — this class is bound in the container
 * and swapped in tests with ArrayMailer or a mock.
 *
 * Failure contract: every method swallows its own exceptions and logs them.
 * Callers (listeners) already wrap calls in try/catch, but this double-guard
 * ensures a mail infrastructure failure never propagates upward regardless
 * of how this class is called in future.
 */
class MailSubscriptionNotificationDispatcher implements SubscriptionNotificationDispatcher
{
    public function __construct(
        private readonly MailManager $mailManager,
        private readonly Logger      $logger,
    )
    {
    }

    public function notifyPaymentFailed(
        Subscription       $subscription,
        \DateTimeImmutable $gracePeriodUntil,
        ?string            $failureReason,
    ): void
    {
        $member = $subscription->member;

        if (!$this->hasValidEmail($member)) {
            $this->logger->warning('MailSubscriptionNotificationDispatcher: member has no email, skipping payment failed notification', [
                'subscription_id' => $subscription->id,
                'member_id' => $member?->id,
            ]);
            return;
        }

        $this->mailManager->send(
            new PaymentFailedMailable($subscription, $gracePeriodUntil, $failureReason)
        );

        $this->logger->info('MailSubscriptionNotificationDispatcher: payment failed notification sent', [
            'subscription_id' => $subscription->id,
            'member_email' => $member->email,
        ]);
    }

    private function hasValidEmail(mixed $member): bool
    {
        return $member !== null
            && $member->email
            && filter_var($member->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function notifySubscriptionCancelled(
        Subscription       $subscription,
        \DateTimeImmutable $accessUntil,
    ): void
    {
        $member = $subscription->member;

        if (!$this->hasValidEmail($member)) {
            $this->logger->warning('MailSubscriptionNotificationDispatcher: member has no email, skipping cancellation notification', [
                'subscription_id' => $subscription->id,
                'member_id' => $member?->id,
            ]);
            return;
        }

        $this->mailManager->send(
            new SubscriptionCancelledMailable($subscription, $accessUntil)
        );

        $this->logger->info('MailSubscriptionNotificationDispatcher: cancellation notification sent', [
            'subscription_id' => $subscription->id,
            'member_email' => $member->email,
        ]);
    }

    public function notifyPaymentReceived(Subscription $subscription): void
    {
        $member = $subscription->member;

        if (!$this->hasValidEmail($member)) {
            $this->logger->warning('MailSubscriptionNotificationDispatcher: member has no email, skipping payment received notification', [
                'subscription_id' => $subscription->id,
                'member_id' => $member?->id,
            ]);
            return;
        }

        $this->mailManager->send(
            new PaymentReceivedMailable($subscription)
        );

        $this->logger->info('MailSubscriptionNotificationDispatcher: payment received notification sent', [
            'subscription_id' => $subscription->id,
            'member_email' => $member->email,
        ]);
    }
}