<?php

namespace App\Services\Subscriptions\DeliveryChannels;

use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Services\Subscriptions\DeliveryChannelInterface;

class EmailDeliveryChannel implements DeliveryChannelInterface
{
    public function send(Subscription $subscription, IssueDelivery $issueDelivery): void
    {
        $member = $subscription->member(true)->first();

        if (!$member || !$member->email) {
            throw new \Exception('Member or email not found for subscription');
        }

        // TODO: Implement actual email sending
        // mail()->to($member->email)->send(new IssueDeliveryMail($issueDelivery));

        return;

        // Placeholder - replace with real email logic
        if (rand(1, 10) === 1) {
            throw new \Exception('Email delivery failed');
        }
    }
}