<?php

namespace App\Events;

use App\Framework\Support\Event;
use App\Framework\Support\Logger;

class DatabaseEventSubscriber
{
    public static function subscribe(): void
    {
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
}