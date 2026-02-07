<?php

namespace App\Services\Subscriptions;

use App\Models\IssueDelivery;
use App\Models\Subscription;

interface DeliveryChannelInterface
{
    public function send(Subscription $subscription, IssueDelivery $issueDelivery): void;
}