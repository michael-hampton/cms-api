<?php

namespace App\Services\Subscriptions\Communications;

use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationScheduleRepository;

class SubscriptionCommunicationService
{
    public function __construct(
        private readonly SubscriptionCommunicationRepository $communications,
        private readonly SubscriptionCommunicationScheduleRepository $schedules,
        private readonly SubscriptionCommunicationDeliveryRepository $deliveries,
    ) {
    }

    public function all(): array
    {
        return $this->communications->allWithSchedules()->toArray();
    }

    public function create(array $data): SubscriptionCommunication
    {
        return $this->communications->create($data);
    }

    public function findWithSchedules(int $id): ?\App\Models\Model
    {
        return $this->communications->findWithSchedules($id);
    }

    public function update(int $id, array $data): ?SubscriptionCommunication
    {
        return $this->communications->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->communications->delete($id);
    }

    public function schedulesForCommunication(int $communicationId): array
    {
        if (!$this->communications->find($communicationId)) {
            throw new \RuntimeException('Subscription communication not found.');
        }

        return $this->schedules->findForCommunication($communicationId)->toArray();
    }

    public function createSchedule(int $communicationId, array $data): SubscriptionCommunicationSchedule
    {
        if (!$this->communications->find($communicationId)) {
            throw new \RuntimeException('Subscription communication not found.');
        }

        return $this->schedules->createForCommunication($communicationId, $data);
    }

    public function updateSchedule(int $id, array $data): ?SubscriptionCommunicationSchedule
    {
        return $this->schedules->update($id, $data);
    }

    public function deleteSchedule(int $id): bool
    {
        return $this->schedules->delete($id);
    }

    public function historyForSubscription(int $subscriptionId): array
    {
        return $this->formatHistory($this->deliveries->getForSubscription($subscriptionId));
    }

    public function historyForCommunication(int $communicationId): array
    {
        if (!$this->communications->find($communicationId)) {
            throw new \RuntimeException('Subscription communication not found.');
        }

        return $this->formatHistory($this->deliveries->getForCommunication($communicationId));
    }

    private function formatHistory($deliveries): array
    {
        return $deliveries->map(fn($delivery) => [
            'id' => $delivery->id,
            'subscription_id' => $delivery->subscription_id,
            'communication' => $delivery->communication?->name,
            'type' => $this->enumValue($delivery->communication?->type),
            'schedule' => $delivery->schedule?->name,
            'channel' => $delivery->channel,
            'status' => $this->enumValue($delivery->status),
            'sent_at' => $delivery->sent_at?->format(DATE_ATOM),
            'opened_at' => $delivery->opened_at?->format(DATE_ATOM),
            'clicked_at' => $delivery->clicked_at?->format(DATE_ATOM),
        ])->toArray();
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
