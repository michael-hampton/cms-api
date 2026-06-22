<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class PrintFulfillmentResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'batch_id' => $this->getAttribute('batch_id'),
            'subscription_issue_fulfilment_id' => $this->getAttribute('subscription_issue_fulfilment_id'),
            'subscription_id' => $this->getAttribute('subscription_id'),
            'full_name' => $this->getAttribute('full_name'),
            'address_line_1' => $this->getAttribute('address_line_1'),
            'address_line_2' => $this->getAttribute('address_line_2'),
            'city' => $this->getAttribute('city'),
            'postcode' => $this->getAttribute('postcode'),
            'country' => $this->getAttribute('country'),
            'tracking_number' => $this->getAttribute('tracking_number'),
            'status' => $this->getAttribute('status'),
            'territory_id' => $this->getAttribute('territory_id'),
            'delivery_address_snapshot' => $this->getAttribute('delivery_address_snapshot') ?? [],
            'subscription_issue_fulfilments' => $this->formatSubscriptionIssueFulfilment(),
            'issue_delivery' => $this->formatIssueDelivery(),
            'batch' => $this->formatBatch(),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * The SubscriptionIssueFulfilment record belongs directly to the PrintFulfillment
     * and tracks delivery status for this subscriber.
     */
    private function formatSubscriptionIssueFulfilment(): ?array
    {
        if (!is_object($this->resource) || !$this->resource->relationLoaded('subscriptionIssueFulfilment')) {
            return null;
        }

        $subscriptionIssueFulfilment = $this->resource->subscriptionIssueFulfilment;

        if (!$subscriptionIssueFulfilment) {
            return null;
        }

        return [
            'id' => $subscriptionIssueFulfilment->id,
            'status' => $subscriptionIssueFulfilment->status,
            'attempts' => $subscriptionIssueFulfilment->attempts,
            'delivered_at' => $this->formatDate($subscriptionIssueFulfilment->delivered_at),
            'failure_reason' => $subscriptionIssueFulfilment->failure_reason,
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return $value->format('Y-m-d H:i:s');
    }

    /**
     * The IssueDelivery record is reached via batch → issueDelivery
     * (not directly on PrintFulfillment).
     */
    private function formatIssueDelivery(): ?array
    {

        return [
            'id' => $this->getAttribute('batch.issueDelivery.id'),
            'issue_title' => $this->getAttribute('batch.issueDelivery.issue_title'),
            'issue_number' => $this->getAttribute('batch.issueDelivery.issue_number'),
            'on_sale_date' => $this->formatDate($this->getAttribute('batch.issueDelivery.on_sale_date')),
            'estimated_delivery_date' => $this->formatDate($this->getAttribute('batch.issueDelivery.estimated_delivery_date')),
            'status' => $this->getAttribute('batch.issueDelivery.status'),
        ];
    }

    private function formatBatch(): ?array
    {

        return [
            'id' => $this->getAttribute('batch.id'),
            'status' => $this->getAttribute('batch.status'),
            'format' => $this->getAttribute('batch.format'),
            'export_attempt_count' => $this->getAttribute('batch.export_attempt_count'),
            'file_path' => $this->getAttribute('batch.file_path'),
            'exported_at' => $this->formatDate($this->getAttribute('batch.exported_at')),
        ];
    }
}