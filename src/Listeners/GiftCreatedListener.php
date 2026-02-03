<?php

namespace App\Listeners;

use App\Events\ArticleGifting\GiftCreatedEvent;
use App\Events\Badges\PointsAwardedEvent;

class GiftCreatedListener
{
    public function handle(GiftCreatedEvent $event): void
    {
        // Access event data
        $member = $event->gift;
    }
}