<?php

namespace App\Enums\Subscriptions;

enum SubscriptionEndReason: string
{
    case RENEWAL = 'renewal';
    case PRODUCT_CHANGE = 'product_change';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case SUSPENDED = 'suspended';
    case PRICE_RISE = 'price_rise';
}