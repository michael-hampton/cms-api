<?php

namespace App\Events\Subscriptions;

use App\Models\Subscription;

final class SubscriptionPaused
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly ?string      $pausedUntil = null,
        public readonly int          $memberId,
    )
    {
    }
}