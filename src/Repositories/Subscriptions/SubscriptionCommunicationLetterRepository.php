<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\LetterBatchStatus;
use App\Enums\Subscriptions\LetterFulfilmentStatus;
use App\Models\SubscriptionCommunicationLetterBatch;
use App\Models\SubscriptionCommunicationLetterFulfilment;

/**
 * Persistence for the letter fulfilment pipeline. Deliberately separate
 * from PrintDeliveryChannel/PrintBatchRepository — those model magazine
 * issue mailings (tied to IssueDelivery), this models ad-hoc correspondence
 * triggered by billing events.
 */
class SubscriptionCommunicationLetterRepository
{
    public function createFulfilment(
        int $deliveryId,
        int $subscriptionId,
        string $letterCode,
        array $resolvedAddress,
    ): SubscriptionCommunicationLetterFulfilment {
        $batch = SubscriptionCommunicationLetterBatch::create([
            'status' => LetterBatchStatus::PENDING->value,
        ]);

        return SubscriptionCommunicationLetterFulfilment::create([
            'subscription_communication_letter_batch_id' => $batch->id,
            'subscription_communication_delivery_id' => $deliveryId,
            'subscription_id' => $subscriptionId,
            'letter_code' => $letterCode,
            'full_name' => $resolvedAddress['full_name'],
            'address_line_1' => $resolvedAddress['address_line_1'],
            'address_line_2' => $resolvedAddress['address_line_2'] ?? null,
            'city' => $resolvedAddress['city'],
            'postcode' => $resolvedAddress['postcode'],
            'country' => $resolvedAddress['country'],
            'address_snapshot' => $resolvedAddress['snapshot'] ?? $resolvedAddress,
            'status' => LetterFulfilmentStatus::PENDING->value,
        ]);
    }
}
