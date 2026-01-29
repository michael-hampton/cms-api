<?php

namespace App\Services\Product\FeedMappers;

interface FeedMapperInterface
{
    /**
     * Map merchant-specific product data to standard product format
     *
     * @param array $productData Raw product data from feed
     * @return array Mapped product data
     */
    public function map(array $productData): array;

    /**
     * Check if this mapper supports the given merchant/URL
     *
     * @param string $url Feed URL
     * @param int $merchantId Merchant ID
     * @return bool
     */
    public function supports(string $url, int $merchantId): bool;
}