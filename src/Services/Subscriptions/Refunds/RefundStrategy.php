<?php

namespace App\Services\Subscriptions\Refunds;

use App\Models\Subscription;

interface RefundStrategy
{
    public function calculate(Subscription $subscription): RefundResult;
}