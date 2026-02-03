<?php
// src/Http/Resources/ProductRecommendationResource.php

namespace App\Resources;

use App\Models\Product;

class ProductRecommendationResource
{
    private const DESCRIPTION_LENGTH = 150;

    public function format(Product $product): array
    {
        $description = $product->description ?? '';

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $this->truncateDescription($description, self::DESCRIPTION_LENGTH),
            'image' => $product->main_image_url ?? $product->image,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'currency' => $product->currency ?? config('recommendations.currency.default'),
            'discount_percentage' => $product->discount_percentage,
            'has_discount' => $product->sale_price && $product->sale_price < $product->price,
        ];
    }

    public function collection(iterable $products): array
    {
        return array_map(fn($product) => $this->format($product), iterator_to_array($products));
    }

    private function truncateDescription(string $description, int $length): string
    {
        if (mb_strlen($description) <= $length) {
            return $description;
        }

        return mb_substr($description, 0, $length) . '...';
    }
}