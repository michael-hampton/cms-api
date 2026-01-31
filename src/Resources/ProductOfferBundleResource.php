<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;
use App\Models\Merchant;
use App\Models\Product;

class ProductOfferBundleResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'total_price' => $this->getAttribute('total_price'),
            'bundle_price' => $this->getAttribute('bundle_price'),
            'discount_percentage' => $this->getAttribute('discount_percentage'),
            'start_date' => $this->getAttribute('start_date')?->format('Y-m-d H:i:s'),
            'end_date' => $this->getAttribute('end_date')?->format('Y-m-d H:i:s'),
            'is_active' => $this->getAttribute('is_active'),
            'status' => $this->getAttribute('status'),
            'rejection_reason' => $this->getAttribute('rejection_reason'),
            'published_at' => $this->getAttribute('published_at')?->format('Y-m-d H:i:s'),
            'published_by' => $this->getAttribute('published_by'),
            'rejected_at' => $this->getAttribute('rejected_at')?->format('Y-m-d H:i:s'),
            'rejected_by' => $this->getAttribute('rejected_by'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
            'created_by' => $this->getAttribute('created_by'),
            'updated_by' => $this->getAttribute('updated_by'),
            'items' => collect($this->getAttribute('items') ?? [])?->map(function ($item) {

                $productId =
                    $item['product_id']
                    ?? $item['productOffer']['product_id']
                    ?? null;

                $merchantId = $item['productOffer']['merchant_id'] ?? null;

                $product = $productId ? Product::find($productId) : null;

                $merchant = $merchantId
                    ? Merchant::find($merchantId)
                    : ($product?->merchants->first() ?? null);

                return [
                    'id' => $item['id'],
                    'product_offer_id' => $item['product_offer_id'] ?? null,
                    'product_id' => $productId ?? null,
                    'quantity' => $item['quantity'],
                    'product_offer' => $item['productOffer'] ? [
                        'id' => $item['productOffer']['id'],
                        'sale_price' => $item['productOffer']['sale_price'],
                        'product' => $product ? [
                            'id' => $product->id,
                            'name' => $product->name,
                            'price' => $product->price,
                        ] : null,
                        'merchant' => $merchant ? [
                            'id' => $merchant->id,
                            'name' => $merchant->name,
                        ] : null,
                    ] : null,
                    'product' => $product ? [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                    ] : null,
                    'merchant' => $merchant ? [
                        'id' => $merchant->id,
                        'name' => $merchant->name,
                    ] : null,
                ];
            })->toArray(),
            //'savings' => $this->calculateSavings(),
        ];
    }
}