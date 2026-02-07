<?php

namespace App\Services\Subscriptions;

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
        $deliveryType = $subscription->delivery_type ?? 'digital';

        if (!isset($this->channels[$deliveryType])) {
            throw new \Exception("No delivery channel registered for type: {$deliveryType}");
        }

        $this->channels[$deliveryType]->send($subscription, $issueDelivery);
    }
}