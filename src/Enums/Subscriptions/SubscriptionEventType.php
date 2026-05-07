<?php

namespace App\Enums\Subscriptions;

enum SubscriptionEventType: string
{
    case CREATED = 'created';
    case RENEWED = 'renewed';
    case PAUSED = 'paused';
    case CANCELLED = 'cancelled';
    case PAYMENT_FAILED = 'payment_failed';
}