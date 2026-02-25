<?php

namespace App\Enums\Gifts;

enum GiftType: string
{
    case PRODUCT = 'product';
    case SUBSCRIPTION = 'subscription';
}