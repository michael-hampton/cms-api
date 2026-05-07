<?php

namespace App\Services\Subscriptions;

use App\Repositories\Subscriptions\SubscriptionEventRepository;

class SubscriptionHistoryService
{
    public function __construct(
        private readonly SubscriptionEventRepository $eventRepository,
    )
    {
    }

    /**
     * Return paginated lifecycle events for a subscription, newest first.
     *
     * @return array{ events: array<int, array>, total: int }
     */
    public function getPaginatedHistory(int $subscriptionId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $rows = $this->eventRepository->findBySubscription(
            $subscriptionId,
            $perPage,
            $offset
        );

        $total = $this->eventRepository->countBySubscription($subscriptionId);

        return [
            'events' => $rows
                ->map(fn($event) => $this->formatEvent($event))
                ->values()
                ->all(),

            'total' => $total,
        ];
    }

    private function formatEvent(object $event): array
    {
        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'occurred_at' => $event->occurred_at instanceof \DateTimeInterface
                ? $event->occurred_at->format('Y-m-d\TH:i:s\Z')
                : $event->occurred_at,
            'metadata' => is_string($event->metadata)
                ? json_decode($event->metadata, true)
                : ($event->metadata ?? null),
        ];
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /**
     * Append a lifecycle event for a subscription.
     *
     * Called exclusively from RecordSubscriptionHistoryListener — not
     * called directly by services, which fire domain events instead.
     */
    public function record(
        int     $subscriptionId,
        string  $eventType,
        array   $metadata = [],
        ?string $occurredAt = null,
    ): void
    {
        $this->eventRepository->create([
            'subscription_id' => $subscriptionId,
            'event_type' => $eventType,
            'metadata' => empty($metadata) ? null : json_encode($metadata),
            'occurred_at' => $occurredAt ?? now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }
}