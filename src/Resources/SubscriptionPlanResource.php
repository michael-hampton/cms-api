<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'site_id' => $this->getAttribute('site_id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'price' => $this->getAttribute('price'),
            'currency' => $this->getAttribute('currency'),
            'billing_period' => $this->getAttribute('billing_period'),
            'entitlement_type' => $this->getAttribute('entitlement_type') ?? 'time',
            'trial_days' => $this->getAttribute('trial_days'),
            'duration_months' => $this->getAttribute('duration_months'),
            'issue_count' => $this->getAttribute('issue_count'),
            'delivery_type' => $this->getAttribute('delivery_type'),
            'features' => $this->getAttribute('features') ?? [],
            'is_active' => (bool)$this->getAttribute('is_active'),
            'is_featured' => (bool)$this->getAttribute('is_featured'),
            'sort_order' => $this->getAttribute('sort_order'),
            'digital_download_url' => $this->getAttribute('digital_download_url'),
            'digital_image_url' => $this->getAttribute('digital_image_url'),
            'print_image_url' => $this->getAttribute('print_image_url'),
            'print_shipping_required' => (bool)$this->getAttribute('print_shipping_required'),
            'includes_insider' => (bool)$this->getAttribute('includes_insider'),
            'is_upgrade_option' => (bool)$this->getAttribute('is_upgrade_option'),
            'upgrade_from_plan_id' => $this->getAttribute('upgrade_from_plan_id'),
            'dispatch_days' => $this->getAttribute('dispatch_days'),
            'release_date' => $this->formatReleaseDate(),
            'pre_release_enabled' => (bool)$this->getAttribute('pre_release_enabled'),
            'categories' => $this->getAttribute('categories') ?? [],
            'tags' => $this->getAttribute('tags') ?? [],
            'premium_access' => $this->getAttribute('premium_access') ?? [],
            'stripe_product_id' => $this->getAttribute('stripe_product_id'),
            'region_sets' => $this->getRegionSets(),
            'region_set_ids' => $this->getRegionSetIds(),
            'lowest_effective_price' => $this->getLowestEffectivePrice(),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }

    private function formatReleaseDate(): ?string
    {
        $value = $this->getAttribute('release_date');

        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return $value->format('Y-m-d H:i:s');
    }

    private function getRegionSets(): array
    {
        // Relation already eager-loaded by the repository search or explicit load()
        if (is_object($this->resource) && $this->resource->relationLoaded('regionSets')) {
            return $this->resource->regionSets
                ->map(fn($rs) => ['id' => $rs->id, 'name' => $rs->name])
                ->toArray();
        }

        return [];
    }

    private function getRegionSetIds(): array
    {
        if (is_object($this->resource) && $this->resource->relationLoaded('regionSets')) {
            return $this->resource->regionSets->pluck('id')->toArray();
        }

        return [];
    }

    /**
     * Delegates to the model method when the resource is an object (normal path),
     * or reads the pre-computed appended attribute when the resource is an array
     * (e.g. after toArray() serialisation through the repository layer).
     *
     * Returns ['min' => float|null, 'tier' => mixed|null].
     */
    private function getLowestEffectivePrice(): array
    {
        if (is_object($this->resource) && method_exists($this->resource, 'getLowestEffectivePrice')) {
            return $this->resource->getLowestEffectivePrice();
        }

        // Array path: the model appends `lowest_effective_price` via lowestEffectivePriceAttribute()
        $appended = $this->getAttribute('lowest_effective_price');

        return is_array($appended) ? $appended : ['min' => null, 'tier' => null];
    }
}
