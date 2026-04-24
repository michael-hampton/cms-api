<?php

declare(strict_types=1);

namespace App\Mail\Subscriptions;

use App\Framework\Mail\Mailable;
use App\Models\Subscription;

/**
 * Optional receipt sent after a successful invoice payment.
 *
 * Stripe sends its own receipt emails by default. Only use this if you
 * want a branded in-product confirmation in addition to Stripe's receipt,
 * or if Stripe receipts are disabled in your dashboard.
 */
class PaymentReceivedMailable extends Mailable
{
    public function __construct(
        private readonly Subscription $subscription,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $member = $this->subscription->member;
        $plan = $this->subscription->plan;
        $periodEnd = $this->subscription->current_period_end;
        $amount = number_format($this->subscription->price, 2);
        $currency = strtoupper($this->subscription->currency ?? 'GBP');
        $planName = $plan->name;
        $accessUntil = $periodEnd
            ? $periodEnd->format('j F Y')
            : 'your next billing date';

        return $this
            ->subject("Payment received — {$planName}")
            ->to($member->email, $member->full_name ?? $member->name ?? '')
            ->markdown($this->buildMarkdown($planName, $amount, $currency, $accessUntil));
    }

    private function buildMarkdown(
        string $planName,
        string $amount,
        string $currency,
        string $accessUntil,
    ): string
    {
        $member = $this->subscription->member;
        $name = $member->full_name ?? $member->name ?? 'there';

        return <<<MARKDOWN
        # Payment received

        Hi {$name},

        Thank you — your payment has been processed successfully.

        @panel(**{$planName}** · {$currency} {$amount})

        Your access is confirmed until **{$accessUntil}**.

        If you have any questions about your subscription, please get in touch.

        @subcopy(You're receiving this email because you have an active subscription with us.)
        MARKDOWN;
    }
}