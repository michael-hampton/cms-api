<?php

namespace App\Events;

use App\Framework\Support\Event;
use App\Framework\Support\Logger;

class DatabaseEventSubscriber
{
    private static bool $subscribed = false;

    public static function subscribe(): void
    {
        // Skip in tests: these listeners only log, and re-subscribing on every
        // ApiApplication boot previously stacked closures for the process life.
        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing') {
            return;
        }

        if (self::$subscribed) {
            return;
        }

        self::$subscribed = true;

        // Listen to all model events globally
        Event::listen('creating.*', function($event) {
            Logger::debug('Model creating', [
                'model' => get_class($event->getModel()),
                'attributes' => $event->getModel()->toArray()
            ]);
        });

        Event::listen('created.*', function($event) {
            Logger::info('Model created', [
                'model' => get_class($event->getModel()),
                'id' => $event->getModel()->id
            ]);
        });

        Event::listen('updated.*', function($event) {
            Logger::info('Model updated', [
                'model' => get_class($event->getModel()),
                'id' => $event->getModel()->id
            ]);
        });

        Event::listen('deleted.*', function($event) {
            Logger::info('Model deleted', [
                'model' => get_class($event->getModel()),
                'id' => $event->getModel()->id
            ]);
        });
    }

    /** @internal test helper */
    public static function resetSubscriptionState(): void
    {
        self::$subscribed = false;
    }
}