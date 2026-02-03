<?php

namespace App\Framework\Events;

class EventDispatcher
{
    protected array $listeners = [];

    public function listen(string $event, $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(object $event): void
    {
        $eventName = $event::class;

        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            if (is_array($listener)) {
                [$class, $method] = $listener;
                $instance = app($class); // auto-instantiate from container
                $instance->$method($event);
            } else {
                $listener($event); // normal callable
            }
        }
    }
}
