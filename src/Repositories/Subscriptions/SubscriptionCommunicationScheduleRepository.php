<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\SubscriptionCommunicationSchedule;

class SubscriptionCommunicationScheduleRepository
{
    public function findActiveForCommunication(int $communicationId): Collection
    {
        return SubscriptionCommunicationSchedule::where('subscription_communication_id', $communicationId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}