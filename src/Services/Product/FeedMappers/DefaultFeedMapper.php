<?php

namespace App\Services\Product\FeedMappers;

class DefaultFeedMapper extends AbstractFeedMapper
{
    /**
     * Map product data using default field names
     *
     * @param array $productData
     * @return array
     */
    public function map(array $productData): array
    {
        return [
            'name' => $this->getString($productData, ['name', 'title']),
            'description' => $this->getString($productData, ['description', 'desc']),
            'price' => $this->getFloat($productData, ['price']),
            'sale_price' => $this->getFloat($productData, ['sale_price', 'salePrice', 'special_price']),
            'sku' => $this->getString($productData, ['sku', 'id', 'product_id']),
            'url' => $this->getString($productData, ['url', 'link', 'product_url']),
            'image' => $this->getString($productData, ['image', 'image_url', 'imageUrl', 'picture']),
            'category' => $this->getString($productData, ['category', 'categories']),
            'brand' => $this->getString($productData, ['brand', 'manufacturer']),
            'in_stock' => $this->getBool($productData, ['in_stock', 'inStock', 'available', 'availability'], true),
        ];
    }

    /**
     * This is the default mapper, supports all merchants unless overridden
     *
     * @param string $url
     * @param int $merchantId
     * @return bool
     */
    public function supports(string $url, int $merchantId): bool
    {
        return true;
    }
}