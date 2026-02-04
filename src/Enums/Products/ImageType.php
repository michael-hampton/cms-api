<?php

namespace App\Enums\Products;

enum ImageType: string
{
    case PRODUCT_MAIN = 'product_main';
    case PRODUCT_GALLERY = 'product_gallery';
    case VARIANT = 'variant';
}