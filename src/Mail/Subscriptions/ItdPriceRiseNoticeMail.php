<?php

namespace App\Mail\Subscriptions;

use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;

class ItdPriceRiseNoticeMail extends SubscriptionLinkedMail
{
    public ?string $deliveryToken = null;

    public function __construct(
        public readonly Member                             $member,
        public readonly Subscription                       $subscription,
        public readonly SubscriptionCommunication          $communication,
        public readonly ?SubscriptionCommunicationSchedule $schedule = null,
        public readonly array                              $metadata = [],
    ) {
    }

    public function subject(): string
    {
        return 'Important information about your subscription price';
    }

    public function build(): static
    {
        return $this->view('emails.subscriptions.itd-price-rise-notice', [
                'member' => $this->member,
                'subscription' => $this->subscription,
                'communication' => $this->communication,
                'schedule' => $this->schedule,
                'metadata' => $this->metadata,
                'letterCode' => $this->metadata['letter_code'] ?? null,
                'oldPrice' => $this->metadata['old_price'] ?? null,
                'newPrice' => $this->metadata['new_price'] ?? null,
                'effectiveDate' => $this->metadata['effective_date'] ?? null,
            ]);
    }
}