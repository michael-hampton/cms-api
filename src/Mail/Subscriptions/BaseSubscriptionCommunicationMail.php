<?php

namespace App\Mail\Subscriptions;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;

/**
 * Base mailable for all subscription communications.
 *
 * Subclasses receive member, subscription, communication, and schedule.
 * The deliveryToken property is set by SubscriptionCommunicationSender
 * before dispatch so BaseCommunicationMail can inject the tracking pixel
 * and rewrite links.
 */
abstract class BaseSubscriptionCommunicationMail extends Mailable
{
    public ?string $deliveryToken = null;

    public function __construct(
        protected readonly Member                               $member,
        protected readonly Subscription                        $subscription,
        protected readonly SubscriptionCommunication           $communication,
        protected readonly ?SubscriptionCommunicationSchedule  $schedule = null,
    ) {
    }

    protected function trackingPixelUrl(): ?string
    {
        if ($this->deliveryToken === null) {
            return null;
        }

        return route('subscription-comms.open', ['token' => $this->deliveryToken]);
    }

    protected function trackingClickUrl(string $destinationUrl): string
    {
        if ($this->deliveryToken === null) {
            return $destinationUrl;
        }

        return route('subscription-comms.click', [
            'token' => $this->deliveryToken,
            'url'   => $destinationUrl,
        ]);
    }
}