<?php

namespace App\Listeners;

use App\Events\Badges\PointsAwardedEvent;

class PointsAwardedListener
{
    public function handle(PointsAwardedEvent $event): void
    {
        // Access event data
        $member = $event->member;
        $points = $event->memberPoint;

        // Example logic
        //echo "Awarding {$points->id} points to user {$member->id}";
    }
}