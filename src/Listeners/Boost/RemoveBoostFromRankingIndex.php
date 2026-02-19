<?php

namespace App\Listeners\Boost;

use App\Events\Boost\BoostCancelledEvent;
use App\Events\Boost\BoostExpiredEvent;
use App\Framework\Support\Logger;

class RemoveBoostFromRankingIndex
{
    public function handle(BoostExpiredEvent|BoostCancelledEvent $event): void
    {
        try {
            // Cache::forget("boost_ranking_{$event->boost->context}");
            Logger::info('Boost removed from ranking index', [
                'boost_id' => $event->boost->id,
                'context' => $event->boost->context,
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to remove boost from ranking index', [
                'boost_id' => $event->boost->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}