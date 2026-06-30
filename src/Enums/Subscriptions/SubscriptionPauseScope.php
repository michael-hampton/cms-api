<?php

namespace App\Enums\Subscriptions;

enum SubscriptionPauseScope: string
{
    case DELIVERY = 'delivery';
    case SUBSCRIPTION = 'subscription';
}