<?php

namespace App\Listeners;

use App\Events\ArticleGifting\GiftClaimedEvent;
use App\Events\ArticleGifting\GiftCreatedEvent;
use App\Events\Badges\PointsAwardedEvent;

class GiftClaimedListener
{
    public function handle(GiftClaimedEvent $event): void
    {
        // Access event data
        $member = $event->gift;
    }
}