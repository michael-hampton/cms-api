<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\CommunicationDeliveryStatus;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Model;
use App\Models\SubscriptionCommunicationDelivery;

class SubscriptionCommunicationDeliveryRepository
{
    public function hasAlreadySent(
        int     $subscriptionId,
        int     $communicationId,
        ?int    $scheduleId,
        ?string $dedupeKey = null,
    ): bool {
        $query = SubscriptionCommunicationDelivery::where('subscription_id', $subscriptionId)
            ->where('subscription_communication_id', $communicationId)
            ->whereIn('status', [
                CommunicationDeliveryStatus::SENT->value,
                CommunicationDeliveryStatus::PENDING->value,
            ]);

        if ($scheduleId === null) {
            $query->whereNull('subscription_communication_schedule_id');
        } else {
            $query->where('subscription_communication_schedule_id', $scheduleId);
        }

        if ($dedupeKey !== null) {
            $query->where('dedupe_key', $dedupeKey);
        }

        return $query->exists();
    }

    public function recordPending(
        int     $subscriptionId,
        int     $memberId,
        int     $communicationId,
        ?int    $scheduleId,
        string  $channel,
        ?int    $segmentId = null,
        ?int    $subscriptionSegmentId = null,
        ?string $recipientEmail = null,
        ?string $subject = null,
        ?array  $metadata = null,
        ?string $dedupeKey = null,
    ): Model {
        return SubscriptionCommunicationDelivery::create([
            'subscription_id'                          => $subscriptionId,
            'member_id'                                => $memberId,
            'subscription_communication_id'            => $communicationId,
            'subscription_communication_schedule_id'   => $scheduleId,
            'channel'                                  => $channel,
            'status'                                   => CommunicationDeliveryStatus::PENDING->value,
            'token'                                    => Str::uuid(),
            'dedupe_key'                               => $dedupeKey,
            'segment_id'                               => $segmentId,
            'subscription_segment_id'                  => $subscriptionSegmentId,
            'recipient_email'                          => $recipientEmail,
            'subject'                                  => $subject,
            'metadata'                                 => $metadata,
        ]);
    }

    public function markSent(int $deliveryId): void
    {
        SubscriptionCommunicationDelivery::where('id', $deliveryId)->update([
            'status'  => CommunicationDeliveryStatus::SENT->value,
            'sent_at' => now_datetime(),
        ]);
    }

    public function markFailed(int $deliveryId, string $reason): void
    {
        SubscriptionCommunicationDelivery::where('id', $deliveryId)->update([
            'status'         => CommunicationDeliveryStatus::FAILED->value,
            'failed_at'      => now_datetime(),
            'failure_reason' => $reason,
        ]);
    }

    public function getForSubscription(int $subscriptionId): Collection
    {
        return SubscriptionCommunicationDelivery::where('subscription_id', $subscriptionId)
            ->with(['communication', 'schedule'])
            ->orderByDesc('id')
            ->get();
    }

    public function getForCommunication(int $communicationId): Collection
    {
        return SubscriptionCommunicationDelivery::where('subscription_communication_id', $communicationId)
            ->with(['communication', 'schedule'])
            ->orderByDesc('id')
            ->get();
    }

    public function findByToken(string $token): ?SubscriptionCommunicationDelivery
    {
        return SubscriptionCommunicationDelivery::where('token', $token)->first();
    }

    public function markOpenedAt(int $deliveryId): void
    {
        SubscriptionCommunicationDelivery::where('id', $deliveryId)
            ->whereNull('opened_at')
            ->update(['opened_at' => now_datetime()]);
    }

    public function markClickedAt(int $deliveryId): void
    {
        SubscriptionCommunicationDelivery::where('id', $deliveryId)
            ->whereNull('clicked_at')
            ->update(['clicked_at' => now_datetime()]);
    }
}
