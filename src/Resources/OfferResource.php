<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(): array
    {
        $regionSets = $this->getAttribute('regionSets');

        $regionSets = $regionSets === null
            ? null
            : (is_array($regionSets) ? collect($regionSets) : $regionSets);

        return [
            'id' => $this->getAttribute('id'),
            'merchant' => $this->getAttribute('merchant'),
            'product_id' => $this->getAttribute('product_id'),
            'merchant_id' => $this->getAttribute('merchant_id'),
            'start_date' => $this->getAttribute('start_date')?->format('Y-m-d H:i:s'),
            'end_date' => $this->getAttribute('end_date')?->format('Y-m-d H:i:s'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'is_active' => $this->getAttribute('is_active'),
            'created_by' => $this->getAttribute('created_by'),
            'updated_by' => $this->getAttribute('updated_by'),
            'status' => $this->getAttribute('status'),
            'rejection_reason' => $this->getAttribute('rejection_reason'),
            'published_at' => $this->getAttribute('published_at'),
            'published_by' => $this->getAttribute('published_by'),
            'rejected_at' => $this->getAttribute('rejected_at'),
            'rejected_by' => $this->getAttribute('rejected_by'),
            'voucher_id' => $this->getAttribute('voucher_id'),
            'sale_price' => $this->getAttribute('sale_price'),
            'product' => $this->getAttribute('product'),
            'region_set_ids' => $regionSets?->pluck('id')->toArray() ?? [],
            'region_sets' => $regionSets?->map(fn($rs) => [
                    'id' => $rs['id'],
                    'name' => $rs['name'],
                ])->toArray() ?? [],
        ];
    }
}