<?php

namespace App\Services\Product\FeedMappers;

class AmazonFeedMapper extends AbstractFeedMapper
{
    /**
     * Map Amazon-specific product data
     *
     * @param array $productData
     * @return array
     */
    public function map(array $productData): array
    {
        return [
            'name' => $this->getString($productData, ['item_name', 'product_name', 'name']),
            'description' => $this->getString($productData, ['item_description', 'description']),
            'price' => $this->getFloat($productData, ['your_price', 'price']),
            'sale_price' => $this->getFloat($productData, ['sale_price']),
            'sku' => $this->getString($productData, ['asin', 'sku']),
            'url' => $this->getString($productData, ['product_url', 'url']),
            'image' => $this->getString($productData, ['main_image_url', 'image']),
            'category' => $this->getString($productData, ['product_category', 'category']),
            'brand' => $this->getString($productData, ['brand_name', 'brand']),
            'in_stock' => $this->getBool($productData, ['in_stock', 'availability'], true),
        ];
    }

    /**
     * Supports Amazon merchants
     *
     * @param string $url
     * @param int $merchantId
     * @return bool
     */
    public function supports(string $url, int $merchantId): bool
    {
        // Check if URL contains amazon domain
        return strpos(strtolower($url), 'amazon') !== false;
    }
}