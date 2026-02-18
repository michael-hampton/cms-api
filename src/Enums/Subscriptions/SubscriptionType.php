<?php

namespace App\Enums\Subscriptions;

enum SubscriptionType: string
{
    case PRINTED = 'print';
    case DIGITAL = 'digital';
}