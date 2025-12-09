<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'short_description' => $this->getAttribute('short_description'),
            'sku' => $this->getAttribute('sku'),
            'price' => $this->getAttribute('price'),
            'sale_price' => $this->getAttribute('sale_price'),
            'cost' => $this->getAttribute('cost'),
            'stock_quantity' => $this->getAttribute('stock_quantity'),
            'low_stock_threshold' => $this->getAttribute('low_stock_threshold'),
            'weight' => $this->getAttribute('weight'),
            'dimensions' => $this->getAttribute('dimensions'),
            'is_active' => $this->getAttribute('is_active'),
            'is_featured' => $this->getAttribute('is_featured'),
            'brand_id' => $this->getAttribute('brand_id'),
            'category_id' => $this->getAttribute('category_id'),
            'site_id' => $this->getAttribute('site_id'),
            'created_at' => $this->getAttribute('created_at')->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')->format('Y-m-d H:i:s'),
            'image' => $this->getAttribute('image'),
            'meta_title' => $this->getAttribute('meta_title'),
            'meta_description' => $this->getAttribute('meta_description'),
            'meta_keywords' => $this->getAttribute('meta_keywords'),
            'deleted_at' => $this->getAttribute('deleted_at'),
            'clone_history' => $this->getAttribute('clone_history'),
            'created_by' => $this->getAttribute('created_by'),
            'updated_by' => $this->getAttribute('updated_by'),
            'discount_percentage' => $this->getAttribute('discount_percentage'),

            // Computed attributes
            'has_sale' => $this->getAttribute('sale_price') !== null && $this->getAttribute('sale_price') < $this->getAttribute('price'),
            'is_low_stock' => $this->getAttribute('stock_quantity') <= $this->getAttribute('low_stock_threshold'),
            'is_out_of_stock' => $this->getAttribute('stock_quantity') <= 0,

            // Relationships
            'brand' => $this->whenLoaded('brand', function () {
                return [
                    'id' => $this->brand['id'],
                    'name' => $this->brand['name'],
                    'slug' => $this->brand['slug'],
                ];
            }),
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category['id'],
                    'name' => $this->category['name'],
                    'slug' => $this->category['slug'],
                ];
            }),
            'images' => $this->whenLoaded('images', function ($images) {
                return $images->map(fn($image) => [
                    'id' => $image['id'],
                    'product_id' => $image['product_id'],
                    'variant_id' => $image['variant_id'],
                    'url' => $image['url'],
                    'alt' => $image['alt'],
                    'sort_order' => $image['sort_order'],
                    'is_primary' => $image['is_primary']
                ])->toArray();
            }),
            'availableMerchants' => $this->whenLoaded('availableMerchants', function ($availableMerchants) {
                return $availableMerchants->map(fn($merchant) => [
                    'id' => $merchant['id'],
                    'merchant_id' => $merchant['merchant_id'],
                    'product_id' => $merchant['product_id'],
                    'variant_id' => $merchant['variant_id'],
                    'variant_sku' => $merchant['variant_sku'],
                    'url' => $merchant['url'],
                    'price' => $merchant['price'],
                    'sale_price' => $merchant['sale_price'],
                    'override_price' => $merchant['override_price'],
                    'last_price_check' => $merchant['last_price_check'],
                    'is_available' => $merchant['is_available'],
                    'effective_price' => $merchant['effective_price'],
                    'effective_sale_price' => $merchant['effective_sale_price'],
                    'effective_sku' => $merchant['effective_sku'] ?? null,
                    'discount_percentage' => $merchant['discount_percentage'],
                    'has_discount' => $merchant['has_discount'],
                    'merchant' => $merchant['merchant'],
                    'variant' => $merchant['variant'] ?? [],
                ]);
            }),
            'specifications' => $this->whenLoaded('specifications', function ($specifications) {
                return $specifications->map(fn($spec) => [
                    'id' => $spec['id'],
                    'product_id' => $spec['product_id'],
                    'category' => $spec['category'],
                    'key' => $spec['key'],
                    'value' => $spec['value'],
                    'sort_order' => $spec['sort_order'],
                ]);
            }),
            'priceHistory' => $this->whenLoaded('priceHistory', function ($priceHistory) {
                return $priceHistory->map(fn($history) => [
                    'id' => $history['id'],
                    'product_id' => $history['product_id'],
                    'merchant_id' => $history['merchant_id'],
                    'price' => $history['price'],
                    'sale_price' => $history['sale_price'],
                    'recorded_at' => $history['recorded_at']->format('Y-m-d H:i:s')
                ]);
            }),
            'activeVariants' => $this->whenLoaded('activeVariants', function ($activeVariants) {
                return $activeVariants->map(fn($variant) => [
                    'id' => $variant['id'],
                    'product_id' => $variant['product_id'],
                    'attributes' => $variant['attributes'],
                    'sku' => $variant['sku'],
                    'name' => $variant['name'],
                    'price' => $variant['price'],
                    'sale_price' => $variant['sale_price'],
                    'final_price' => $variant['final_price'],
                    'discount_percentage' => $variant['discount_percentage'],
                    'price_modifier' => $variant['price_modifier'],
                    'images' => $variant['images'] ?? [],
                    'is_active' => $variant['is_active']
                ])->toArray();
            }),
        ];
    }
}