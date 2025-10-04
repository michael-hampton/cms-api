<?php

namespace App\Framework\Support;

class Event
{
    private static $listeners = [];

    public static function listen(string $event, callable $listener): void
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = $listener;
    }

    public static function fire(string $event, array $data = []): void
    {
        if (!isset(self::$listeners[$event])) {
            return;
        }

        foreach (self::$listeners[$event] as $listener) {
            $listener($data);
        }
    }
}