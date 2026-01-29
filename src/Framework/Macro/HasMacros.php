<?php

namespace App\Framework\Macro;

trait HasMacros
{
    protected static array $macros = [];

    public static function macro(string $name, callable $callback): void
    {
        static::$macros[$name] = $callback;
    }

    protected function hasMacro(string $method): bool
    {
        return isset(static::$macros[$method]);
    }

    protected function callMacro(string $method, array $arguments)
    {
        $macro = static::$macros[$method];
        return $macro->call($this, $this, ...$arguments);
    }
}