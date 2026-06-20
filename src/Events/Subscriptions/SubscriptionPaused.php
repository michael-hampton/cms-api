<?php

namespace App\Events\Subscriptions;

use App\Models\Subscription;

final class SubscriptionPaused
{
    public int $durationMonths = 0;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly ?string      $pausedUntil = null,
        public readonly ?int $memberId = null,
        public string           $pauseStart = '',
        public readonly ?string $reason = null,
    )
    {
        if (empty($this->pauseStart)) {
            $this->pauseStart = now();
        }

        // Derive duration in whole months for the history entry.
        $start = new \DateTime($pauseStart);

        if(!empty($this->pausedUntil)) {
            $end = new \DateTime($this->pausedUntil);
            $this->durationMonths = (int)$start->diff($end)->m
                + ((int)$start->diff($end)->y * 12);
        }
    }
}