<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\PrintFulfillmentStatus;
use App\Models\Model;
use App\Models\PrintFulfillment;

class PrintFulfillmentRepository
{
    public function create(
        int     $batchId,
        int     $issuesDeliveredId,
        int     $subscriptionId,
        string  $fullName,
        array   $addressSnapshot,
        string  $addressLine1,
        ?string $addressLine2,
        string  $city,
        string  $postcode,
        string  $country
    ): Model
    {
        return PrintFulfillment::create([
            'batch_id' => $batchId,
            'issues_delivered_id' => $issuesDeliveredId,
            'subscription_id' => $subscriptionId,
            'full_name' => $fullName,
            'delivery_address_snapshot' => $addressSnapshot,
            'address_line_1' => $addressLine1,
            'address_line_2' => $addressLine2,
            'city' => $city,
            'postcode' => $postcode,
            'country' => $country,
            'status' => PrintFulfillmentStatus::QUEUED->value,
        ]);
    }

    /**
     * @return PrintFulfillment[]
     */
    public function findByBatch(int $batchId): array
    {
        return PrintFulfillment::where('batch_id', $batchId)->get()->all();
    }

    public function markAllExported(int $batchId): void
    {
        PrintFulfillment::where('batch_id', $batchId)
            ->update(['status' => PrintFulfillmentStatus::EXPORTED->value]);
    }
}