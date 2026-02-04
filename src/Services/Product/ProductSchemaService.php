<?php

namespace App\Services\Product;

use App\Models\Product;

class ProductSchemaService
{
    public function generateStructuredData(Product $product): array
    {
        return [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->description,
            'image' => $product->main_image_url ?? $product->image_url,
            'brand' => [
                '@type' => 'Brand',
                'name' => $product->brand->name ?? 'Unknown'
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => $product->sale_price ?? $product->price,
                'priceCurrency' => 'USD',
                'availability' => $product->in_stock ? 'InStock' : 'OutOfStock',
                'url' => '/products/' . $product->slug
            ]
        ];
    }
}