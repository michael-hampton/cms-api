<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\SubscriptionCommunicationSchedule;

class SubscriptionCommunicationScheduleRepository
{
    public function find(int $id): ?SubscriptionCommunicationSchedule
    {
        return SubscriptionCommunicationSchedule::find($id);
    }

    public function findActiveForCommunication(int $communicationId): Collection
    {
        return SubscriptionCommunicationSchedule::where('subscription_communication_id', $communicationId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function findForCommunication(int $communicationId): Collection
    {
        return SubscriptionCommunicationSchedule::where('subscription_communication_id', $communicationId)
            ->orderBy('sort_order')
            ->get();
    }

    public function createForCommunication(int $communicationId, array $data): SubscriptionCommunicationSchedule
    {
        return SubscriptionCommunicationSchedule::create(array_merge($data, [
            'subscription_communication_id' => $communicationId,
        ]));
    }

    public function update(int $id, array $data): ?SubscriptionCommunicationSchedule
    {
        $schedule = $this->find($id);

        if (!$schedule) {
            return null;
        }

        $schedule->update($data);

        return $schedule;
    }

    public function delete(int $id): bool
    {
        $schedule = $this->find($id);

        return $schedule ? $schedule->delete() : false;
    }
}
