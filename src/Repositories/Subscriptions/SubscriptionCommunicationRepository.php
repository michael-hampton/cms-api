<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\CommunicationTypeEnum;
use App\Framework\Support\Collection;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;

class SubscriptionCommunicationRepository
{
    public function allWithSchedules(): Collection
    {
        return SubscriptionCommunication::with('schedules')->orderBy('sort_order')->get();
    }

    public function create(array $data): SubscriptionCommunication
    {
        return SubscriptionCommunication::create($data);
    }

    public function find(int $id): ?SubscriptionCommunication
    {
        return SubscriptionCommunication::find($id);
    }

    public function update(int $id, array $data): ?SubscriptionCommunication
    {
        $communication = $this->find($id);

        if (!$communication) {
            return null;
        }

        $communication->update($data);

        return $communication;
    }

    public function delete(int $id): bool
    {
        $communication = $this->find($id);

        return $communication ? $communication->delete() : false;
    }

    public function findActiveForSegment(?int $segmentId): Collection
    {
        return SubscriptionCommunication::where('is_active', true)
            ->where(function ($q) use ($segmentId) {
                $q->whereNull('segment_id');
                if ($segmentId !== null) {
                    $q->orWhere('segment_id', $segmentId);
                }
            })
            ->orderBy('sort_order')
            ->get();
    }

    public function findActiveByType(CommunicationTypeEnum $type): Collection
    {
        return SubscriptionCommunication::where('is_active', true)
            ->where('type', $type->value)
            ->orderBy('sort_order')
            ->get();
    }

    public function findWithSchedules(int $id): ?\App\Models\Model
    {
        $communication = SubscriptionCommunication::find($id);

        if (!$communication) {
            return null;
        }

        $communication->schedules = SubscriptionCommunicationSchedule::query()
            ->where('subscription_communication_id', $communication->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $communication;
    }

    public function findActiveByKey(string $key): ?SubscriptionCommunication
    {
        return SubscriptionCommunication::where('key', $key)
            ->where('is_active', true)
            ->first();
    }
}
