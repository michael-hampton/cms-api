<?php

namespace App\Jobs;

use App\Framework\Database\Database;

abstract class BaseJob
{
    /**
     * Creates a job instance with default dependencies automatically.
     * Uses reflection to inspect constructor, auto-resolves Database singleton,
     * and blindly calls `new $type()` for other classes.
     */
    public static function for(): static
    {
        $constructor = new \ReflectionClass(static::class);
        $params = $constructor->getConstructor()?->getParameters() ?? [];

        $dependencies = [];

        foreach ($params as $param) {
            $type = $param->getType()?->getName() ?? null;

            if ($type === Database::class) {
                // singleton DB
                $dependencies[] = Database::getInstance();
            } elseif ($type && class_exists($type)) {
                // naive reflection for everything else
                $dependencies[] = new $type();
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "Cannot resolve constructor parameter '{$param->name}' of type '{$type}' for job "
                    . static::class
                );
            }
        }

        return $constructor->newInstanceArgs($dependencies);
    }
}
