<?php

declare(strict_types=1);

namespace App\Mail\Subscriptions;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPricingChange;

/**
 * Sent to a subscriber to notify them of an upcoming price change.
 *
 * Provides 30 days notice in compliance with DMCC Act guidance and
 * Consumer Rights Act 2015 obligations for subscription price increases.
 *
 * The email must clearly state:
 *  - The current price
 *  - The new price
 *  - The date the new price takes effect
 *  - How the subscriber can cancel before that date
 */
class PricingChangeNoticeMail extends Mailable
{
    public function __construct(
        private readonly Member                    $member,
        private readonly Subscription              $subscription,
        private readonly SubscriptionPricingChange $pricingChange,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $planName = $this->subscription->plan_name ?? $this->pricingChange->plan()?->name ?? 'your subscription';
        $effectiveDate = $this->pricingChange->effective_date->format('j F Y');

        if (empty($this->member) || empty($this->member->email)) {
            return $this;
        }

        return $this
            ->to($this->member->email ?? 'test')
            ->subject("Important: Price change to your {$planName} subscription")
            ->markdown('emails.subscriptions.pricing-change-notice', [
                'member' => $this->member,
                'subscription' => $this->subscription,
                'pricingChange' => $this->pricingChange,
                'planName' => $planName,
                'oldPrice' => $this->pricingChange->old_price,
                'newPrice' => $this->pricingChange->new_price,
                'currency' => $this->pricingChange->currency,
                'effectiveDate' => $effectiveDate,
                'managementUrl' => $this->buildManagementUrl(),
                'cancellationUrl' => $this->buildCancellationUrl(),
            ]);
    }

    private function buildManagementUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/account/subscriptions/' . $this->subscription->id;
    }

    private function buildCancellationUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/account/subscriptions/' . $this->subscription->id . '/cancel';
    }
}