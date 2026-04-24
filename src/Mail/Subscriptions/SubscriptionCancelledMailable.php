<?php

declare(strict_types=1);

namespace App\Mail\Subscriptions;

use App\Framework\Mail\Mailable;
use App\Models\Subscription;

/**
 * Sent when customer.subscription.deleted is received from Stripe.
 *
 * Key message: the member retains access until the end of the period
 * they have already paid for. Access does not end today.
 */
class SubscriptionCancelledMailable extends Mailable
{
    public function __construct(
        private readonly Subscription       $subscription,
        private readonly \DateTimeImmutable $accessUntil,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $member = $this->subscription->member;
        $planName = $this->subscription->plan->name;

        return $this
            ->subject("Your {$planName} has been cancelled")
            ->to($member->email, $member->full_name ?? $member->name ?? '')
            ->markdown($this->buildMarkdown($planName));
    }

    private function buildMarkdown(string $planName): string
    {
        $member = $this->subscription->member;
        $name = $member->full_name ?? $member->name ?? 'there';
        $accessDate = $this->accessUntil->format('j F Y');

        return <<<MARKDOWN
        # Your subscription has been cancelled

        Hi {$name},

        Your **{$planName}** subscription has been cancelled.

        @panel(You will continue to have full access until **{$accessDate}**. No further payments will be taken.)

        After {$accessDate}, your access to premium content will end automatically.

        ---

        ### Changed your mind?

        If you cancelled by mistake, or would like to resubscribe, you can do so from your account at any time.

        @divider

        Thank you for being a subscriber. We hope to see you again.

        @subcopy(You're receiving this email because your subscription was cancelled. If you didn't request this, please contact us immediately.)
        MARKDOWN;
    }
}