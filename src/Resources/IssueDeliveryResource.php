<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class IssueDeliveryResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'subscription_id' => $this->getAttribute('subscription_id'),
            'subscription_plan_id' => $this->getAttribute('subscription_plan_id'),
            'site_id' => $this->getAttribute('site_id'),
            'issue_number' => $this->getAttribute('issue_number'),
            'issue_title' => $this->getAttribute('issue_title'),
            'issue_code' => $this->getAttribute('issue_code'),
            'on_sale_date' => $this->getAttribute('on_sale_date')?->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => $this->getAttribute('estimated_delivery_date')?->format('Y-m-d H:i:s'),
            'cut_off_date' => $this->getAttribute('cut_off_date')?->format('Y-m-d'),
            'fulfilment_date' => $this->getAttribute('fulfilment_date')?->format('Y-m-d'),
            'status' => $this->getAttribute('status'),
            'status_label' => $this->getAttribute('status_label') ?? '',
            'promotion_id' => $this->getAttribute('promotion_id'),
            'tracking_info' => $this->getAttribute('tracking_info'),
            'metadata' => $this->getAttribute('metadata'),
            'stock_quantity' => $this->getAttribute('stock_quantity'),
            'preorder_enabled' => $this->getAttribute('preorder_enabled'),
            'restock_date' => $this->getAttribute('restock_date')?->format('Y-m-d H:i:s'),
            'dispatched_at' => $this->getAttribute('dispatched_at')?->format('Y-m-d H:i:s'),
            'dispatched_failed_at' => $this->getAttribute('dispatched_failed_at')?->format('Y-m-d H:i:s'),

            // Skip fields
            'skip_reason' => $this->getAttribute('skip_reason'),
            'skip_notes' => $this->getAttribute('skip_notes'),
            'skipped_by' => $this->getAttribute('skipped_by'),
            'skipped_at' => $this->getAttribute('skipped_at')?->format('Y-m-d H:i:s'),

//            // Computed
//            'can_be_skipped'           => $this->resource->canBeSkipped(),
//            'is_skipped'               => $this->resource->isSkipped(),
//            'is_upcoming'              => $this->resource->isUpcoming(),
//            'is_overdue'               => $this->resource->isOverdue(),

            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),

            // Relationships
            'subscription' => $this->whenLoaded('subscription', function () {
                return [
                    'id' => $this->subscription['id'],
                    'status' => $this->subscription['status'],
                ];
            }),
        ];
    }
}