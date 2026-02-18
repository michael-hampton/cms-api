<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\Subscription;

class DeliveryService
{
    private array $channels = [];

    public function registerChannel(string $type, DeliveryChannelInterface $channel): void
    {
        $this->channels[$type] = $channel;
    }

    public function send(Subscription $subscription, IssueDelivery $issueDelivery): void
    {
        $deliveryType = $subscription->delivery_type ?? SubscriptionType::DIGITAL->value;

        if (!isset($this->channels[$deliveryType])) {
            throw new \Exception("No delivery channel registered for type: {$deliveryType}");
        }

        $this->channels[$deliveryType]->send($subscription, $issueDelivery);
    }
}