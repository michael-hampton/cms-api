<?php

namespace App\Framework\Events;

class EventDispatcher
{
    protected array $listeners = [];
    protected array $wildcardListeners = [];
    protected bool $shouldQueue = false;

    public function listen(string $event, $listener): void
    {
        if (str_contains($event, '*')) {
            $this->wildcardListeners[$event][] = $listener;
        } else {
            $this->listeners[$event][] = $listener;
        }
    }

    public function dispatch(object $event): void
    {
        $eventName = $event::class;

        // Dispatch to exact listeners
        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            $this->callListener($listener, $event);
        }

        // Dispatch to wildcard listeners
        foreach ($this->wildcardListeners as $pattern => $listeners) {
            if ($this->matchesPattern($pattern, $eventName)) {
                foreach ($listeners as $listener) {
                    $this->callListener($listener, $event);
                }
            }
        }
    }

    protected function callListener($listener, object $event): void
    {
        if (is_array($listener)) {
            [$target, $method] = $listener;

            if (is_object($target)) {
                // Already an instance
                $target->$method($event);
            } else {
                // Class name — resolve via container
                $instance = app($target);
                $instance->$method($event);
            }

            return;
        }

        if (is_callable($listener)) {
            $listener($event);
        }
    }

    protected function matchesPattern(string $pattern, string $eventName): bool
    {
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);
        return (bool)preg_match('#^' . $pattern . '$#', $eventName);
    }

    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]) ||
            !empty($this->getWildcardListeners($event));
    }

    protected function getWildcardListeners(string $event): array
    {
        $wildcards = [];
        foreach ($this->wildcardListeners as $pattern => $listeners) {
            if ($this->matchesPattern($pattern, $event)) {
                $wildcards = array_merge($wildcards, $listeners);
            }
        }
        return $wildcards;
    }
}
