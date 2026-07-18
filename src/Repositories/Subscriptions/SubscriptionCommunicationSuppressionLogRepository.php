<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Models\SubscriptionCommunicationSuppressionLog;

class SubscriptionCommunicationSuppressionLogRepository
{
    public function log(
        int $subscriptionId,
        ?int $memberId,
        ?int $communicationId,
        ?string $channel,
        string $reason,
        array $metadata = [],
    ): SubscriptionCommunicationSuppressionLog {
        return SubscriptionCommunicationSuppressionLog::create([
            'subscription_id' => $subscriptionId,
            'member_id' => $memberId,
            'subscription_communication_id' => $communicationId,
            'channel' => $channel,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    public function forSubscription(int $subscriptionId)
    {
        return SubscriptionCommunicationSuppressionLog::where('subscription_id', $subscriptionId)
            ->orderByDesc('created_at')
            ->get();
    }
}
