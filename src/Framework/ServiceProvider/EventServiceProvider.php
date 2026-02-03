<?php

namespace App\Framework\ServiceProvider;

use App\Events\ArticleGifting\GiftClaimedEvent;
use App\Events\ArticleGifting\GiftCreatedEvent;
use App\Events\Badges\PointsAwardedEvent;
use App\Framework\Container;
use App\Framework\Events\EventDispatcher;

class EventServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        // echo 'here';

//        $dispatcher = Container::getInstance()->resolve(EventDispatcher::class);
//
//        $dispatcher->listen(PointsAwardedEvent::class, function (PointsAwardedEvent $event) {
//           die('points awarded');
//        });
//
//        $dispatcher->listen(GiftCreatedEvent::class, function (GiftCreatedEvent $event) {
//            die('gift created');
//        });
//
//        $dispatcher->listen(GiftClaimedEvent::class, function (GiftClaimedEvent $event) {
//            die('gift claimed');
//        });

    }
}