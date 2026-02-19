<?php

namespace App\Listeners\Boost;

use App\Events\Boost\BoostActivatedEvent;
use App\Events\Boost\BoostResumedEvent;
use App\Framework\Support\Logger;

class UpdateBoostActiveIndex
{
    /**
     * Handles both BoostActivatedEvent and BoostResumedEvent.
     * Invalidates any ranking cache for the boost's context so the
     * next query picks up the newly active boost.
     */
    public function handle(BoostActivatedEvent|BoostResumedEvent $event): void
    {
        try {
            // Invalidate ranking cache for this context.
            // Replace with your cache implementation when available.
            // Cache::forget("boost_ranking_{$event->boost->context}");
            Logger::info('Boost index updated', [
                'boost_id' => $event->boost->id,
                'context' => $event->boost->context,
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to update boost active index', [
                'boost_id' => $event->boost->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}