<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->resource['product_id'],
            'sku' => $this->resource['sku'],
            'name' => $this->resource['name'],
            'attributes' => $this->resource['attributes'] ?? [],
            'price' => $this->resource['price'],
            'sale_price' => $this->resource['sale_price'],
            'price_modifier' => $this->resource['price_modifier'],
            'is_active' => $this->resource['is_active'],
            'final_price' => $this->resource['final_price'],
            'discount_percentage' => $this->resource['discount_percentage'],
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->resource['product']['id'],
                    'name' => $this->resource['product']['name'],
                    'slug' => $this->resource['product']['slug'],
                    'price' => $this->resource['product']['price'],
                ];
            }),
            'images' => $this->whenLoaded('images', function () {
                return collect($this->resource['images'])->map(function ($image) {
                    return [
                        'id' => $image['id'],
                        'url' => $image['url'],
                        'alt' => $image['alt'],
                        'is_primary' => $image['is_primary'],
                        'sort_order' => $image['sort_order'],
                    ];
                });
            }),
            'merchants' => $this->whenLoaded('merchants', function () {
                return $this->resource->merchants->map(function ($merchant) {
                    return [
                        'id' => $merchant['id'],
                        'merchant_id' => $merchant['merchant_id'],
                        'url' => $merchant['url'],
                        'price' => $merchant['price'],
                        'is_available' => $merchant['is_available']
                    ];
                });
            }),
            'created_at' => $this->resource['created_at']?->format('Y-m-d H:i:s'),
            'updated_at' => $this->resource['updated_at']?->format('Y-m-d H:i:s')
        ];
    }
}