<?php

declare(strict_types=1);

namespace App\Notifications\Subscriptions;

use App\Framework\Notifications\MailableNotification;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Mail\Subscriptions\PaymentFailedMailable;
use App\Mail\Subscriptions\PaymentReceivedMailable;
use App\Mail\Subscriptions\SubscriptionCancelledMailable;
use App\Models\Subscription;

/**
 * Sends subscription notification emails through the notification pipeline.
 *
 * Routing these emails via NotificationDispatcher keeps subscription lifecycle
 * mail on the same path as campaign/lifecycle communication emails:
 *
 *   Subscription listener
 *      -> SubscriptionNotificationDispatcher
 *      -> NotificationDispatcher
 *      -> EmailChannel
 *      -> EmailNotificationSent
 *      -> communication_logs
 */
class MailSubscriptionNotificationDispatcher implements SubscriptionNotificationDispatcher
{
    public function __construct(
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly Logger                 $logger,
    ) {
    }

    public function notifyPaymentFailed(
        Subscription       $subscription,
        \DateTimeImmutable $gracePeriodUntil,
        ?string            $failureReason,
    ): void {
        $member = $subscription->member;

        if (!$this->hasValidEmail($member)) {
            $this->logger->warning('MailSubscriptionNotificationDispatcher: member has no email, skipping payment failed notification', [
                'subscription_id' => $subscription->id,
                'member_id' => $member?->id,
            ]);
            return;
        }

        $mailable = new PaymentFailedMailable($subscription, $gracePeriodUntil, $failureReason);
        $sent = $this->notificationDispatcher->dispatch(new MailableNotification(
            mailable: $mailable,
            email: $member->email,
            subject: 'Subscription payment failed',
            userId: (int) $member->id,
        ));

        if ($sent === 0) {
            $this->logger->warning('MailSubscriptionNotificationDispatcher: payment failed notification was not sent', [
                'subscription_id' => $subscription->id,
                'member_email' => $member->email,
            ]);
            return;
        }

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
    ): void {
        $member = $subscription->member;

        if (!$this->hasValidEmail($member)) {
            $this->logger->warning('MailSubscriptionNotificationDispatcher: member has no email, skipping cancellation notification', [
                'subscription_id' => $subscription->id,
                'member_id' => $member?->id,
            ]);
            return;
        }

        $mailable = new SubscriptionCancelledMailable($subscription, $accessUntil);
        $sent = $this->notificationDispatcher->dispatch(new MailableNotification(
            mailable: $mailable,
            email: $member->email,
            subject: 'Subscription cancelled',
            userId: (int) $member->id,
        ));

        if ($sent === 0) {
            $this->logger->warning('MailSubscriptionNotificationDispatcher: cancellation notification was not sent', [
                'subscription_id' => $subscription->id,
                'member_email' => $member->email,
            ]);
            return;
        }

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

        $mailable = new PaymentReceivedMailable($subscription);
        $sent = $this->notificationDispatcher->dispatch(new MailableNotification(
            mailable: $mailable,
            email: $member->email,
            subject: 'Subscription payment received',
            userId: (int) $member->id,
        ));

        if ($sent === 0) {
            $this->logger->warning('MailSubscriptionNotificationDispatcher: payment received notification was not sent', [
                'subscription_id' => $subscription->id,
                'member_email' => $member->email,
            ]);
            return;
        }

        $this->logger->info('MailSubscriptionNotificationDispatcher: payment received notification sent', [
            'subscription_id' => $subscription->id,
            'member_email' => $member->email,
        ]);
    }
}
