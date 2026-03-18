<?php

declare(strict_types=1);

namespace App\Enums\Alerts;

enum AlertableEntityType: string
{
    case ProductOffer = 'product_offer';
    case ProductOfferBundle = 'product_offer_bundle';
    case GiftPromotion = 'gift_promotion';
}