<?php

namespace App\Services\Product\FeedMappers;

class EbayFeedMapper extends AbstractFeedMapper
{
    /**
     * Map eBay-specific product data
     *
     * @param array $productData
     * @return array
     */
    public function map(array $productData): array
    {
        return [
            'name' => $this->getString($productData, ['Title', 'name']),
            'description' => $this->getString($productData, ['Description', 'description']),
            'price' => $this->getFloat($productData, ['CurrentPrice', 'price']),
            'sale_price' => $this->getFloat($productData, ['SalePrice', 'sale_price']),
            'sku' => $this->getString($productData, ['ItemID', 'SKU', 'sku']),
            'url' => $this->getString($productData, ['ViewItemURL', 'url']),
            'image' => $this->getString($productData, ['PictureURL', 'image']),
            'category' => $this->getString($productData, ['PrimaryCategory', 'category']),
            'brand' => $this->getString($productData, ['Brand', 'brand']),
            'in_stock' => $this->getBool($productData, ['in_stock', 'QuantityAvailable'], false) || $this->getInteger($productData, ['QuantityAvailable']) > 0,
        ];
    }

    /**
     * Supports eBay merchants
     *
     * @param string $url
     * @param int $merchantId
     * @return bool
     */
    public function supports(string $url, int $merchantId): bool
    {
        return strpos(strtolower($url), 'ebay') !== false;
    }
}