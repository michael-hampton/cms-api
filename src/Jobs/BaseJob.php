<?php

namespace App\Jobs;

use App\Framework\Database\Database;

abstract class BaseJob
{
    public static function for(): static
    {
        return static::resolve(static::class);
    }

    private static function resolve(string $class)
    {
        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $class();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                    continue;
                }

                throw new \RuntimeException(
                    "Cannot resolve parameter '{$param->getName()}' in {$class}"
                );
            }

            $typeName = $type->getName();

            if ($typeName === Database::class) {
                $dependencies[] = Database::getInstance();
            } else {
                $dependencies[] = static::resolve($typeName); // 🔥 recursive
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }

}
