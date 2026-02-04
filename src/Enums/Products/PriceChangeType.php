<?php

namespace App\Enums\Products;

enum PriceChangeType: string
{
    case PRODUCT_BASE_PRICE = 'product_base_price';
    case PRODUCT_SALE_PRICE = 'product_sale_price';
    case MERCHANT_PRICE = 'merchant_price';
    case VARIANT_PRICE = 'variant_price';
}