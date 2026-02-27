<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class GiftPromotionResource extends JsonResource
{
    public function toArray(): array
    {
        $triggers = is_array($this->resource) ? collect($this->resource['triggers']) : collect($this->resource->triggers->toArray());

        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'merchant_id' => $this->getAttribute('merchant_id'),
            'gift_type' => $this->getAttribute('gift_type'),
            'gift_product_id' => $this->getAttribute('gift_product_id'),
            'gift_subscription_plan_id' => $this->getAttribute('gift_subscription_plan_id'),
            'quantity_rule' => $this->getAttribute('quantity_rule'),
            'max_per_order' => $this->getAttribute('max_per_order'),
            'exclusive' => $this->getAttribute('exclusive'),
            'priority' => $this->getAttribute('priority'),
            'active' => $this->getAttribute('active'),
            'starts_at' => $this->getAttribute('starts_at')?->format('Y-m-d H:i:s'),
            'ends_at' => $this->getAttribute('ends_at')?->format('Y-m-d H:i:s'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),

            // Computed
            'is_currently_active' => $this->isCurrentlyActive(),
            'trigger_count' => $this->getTriggerCount(),
            'excluded_issue_count' => $this->getExcludedIssueCount(),
            'triggers' => $triggers->map(fn($trigger) => [
                'id' => $trigger['id'],
                'type' => $trigger['type'],
                'operator' => $trigger['operator'],
                'reference_id' => $trigger['reference_id'],
                'value' => $trigger['value'],
                'value_set' => $trigger['value_set'],
                'group_key' => $trigger['group_key'],
                'negated' => $trigger['negated'],
            ])->toArray(),

            'excluded_issue_ids' => $this->whenLoaded('issueExclusions', function () {
                return $this->getAttribute('issueExclusions')
                    ->pluck('issue_delivery_id')
                    ->toArray();
            }),
        ];
    }

    private function isCurrentlyActive(): bool
    {
        if (!$this->getAttribute('active')) {
            return false;
        }

        $now = now();
        $startsAt = $this->getAttribute('starts_at');
        $endsAt = $this->getAttribute('ends_at');

        if ($startsAt && $startsAt > $now) {
            return false;
        }

        if ($endsAt && $endsAt < $now) {
            return false;
        }

        return true;
    }

    private function getTriggerCount(): int
    {
        if (is_array($this->resource) && isset($this->resource['triggers_count'])) {
            return (int)$this->resource['triggers_count'];
        }

        if (is_object($this->resource) && $this->resource->relationLoaded('triggers')) {
            return $this->resource->triggers->count();
        }

        return 0;
    }

    private function getExcludedIssueCount(): int
    {
        if (is_array($this->resource) && isset($this->resource['issue_exclusions_count'])) {
            return (int)$this->resource['issue_exclusions_count'];
        }

        if (is_object($this->resource) && $this->resource->relationLoaded('issueExclusions')) {
            return $this->resource->issueExclusions->count();
        }

        return 0;
    }
}