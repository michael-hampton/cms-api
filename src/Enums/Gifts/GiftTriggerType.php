<?php

namespace App\Enums\Gifts;

enum GiftTriggerType: string
{
    case PRODUCT = 'product';
    case SUBSCRIPTION_PLAN = 'subscription_plan';
    case CART_TOTAL = 'cart_total';
    case ITEM_COUNT = 'item_count';
    case CATEGORY = 'category';
    case FIRST_TIME_BUYER = 'first_time_buyer';
}