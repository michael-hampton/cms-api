<?php

declare(strict_types=1);

namespace App\Mail\Subscriptions;

use App\Framework\Mail\Mailable;
use App\Models\Subscription;

/**
 * Sent when invoice.payment_failed is received from Stripe.
 *
 * The message is deliberately reassuring:
 *   - Stripe retries automatically — don't alarm the member
 *   - Communicate the grace period so they know access is not lost yet
 *   - Give them a clear action (update payment details) if retries fail
 */
class PaymentFailedMailable extends Mailable
{
    public function __construct(
        private readonly Subscription       $subscription,
        private readonly \DateTimeImmutable $gracePeriodUntil,
        private readonly ?string            $failureReason,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $member = $this->subscription->member;
        $planName = $this->subscription->plan->name;

        return $this
            ->subject("Action needed: payment issue with your {$planName}")
            ->to($member->email, $member->full_name ?? $member->name ?? '')
            ->markdown($this->buildMarkdown($planName));
    }

    private function buildMarkdown(string $planName): string
    {
        $member = $this->subscription->member;
        $name = $member->full_name ?? $member->name ?? 'there';
        $graceDate = $this->gracePeriodUntil->format('j F Y');
        $amount = number_format($this->subscription->price, 2);
        $currency = strtoupper($this->subscription->currency ?? 'GBP');

        $reasonLine = $this->failureReason
            ? "\n\nThe reason given was: **{$this->failureReason}**\n"
            : '';

        return <<<MARKDOWN
        # We couldn't process your payment

        Hi {$name},

        We weren't able to collect your payment of **{$currency} {$amount}** for your **{$planName}**.{$reasonLine}

        **Don't worry — your access is not affected yet.**

        We'll automatically retry your payment over the coming days. If the retry succeeds, nothing changes on your end.

        @panel(Your current access remains active until **{$graceDate}**.)

        If you'd like to update your payment details before the next retry, you can do so from your account settings.

        @divider

        If we're unable to collect payment by **{$graceDate}**, your subscription will be suspended.

        @subcopy(You're receiving this email because a payment for your subscription could not be processed. If you believe this is an error, please contact us.)
        MARKDOWN;
    }
}