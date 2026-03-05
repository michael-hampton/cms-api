<?php

namespace App\Jobs;

use App\Framework\Container;

abstract class BaseJob
{
    public static function for(): static
    {
        return static::resolve(static::class);
    }

    private static function resolve(string $class)
    {
        $container = Container::getInstance();
        return $container->resolve(static::class);
//        $container = Container::getInstance();
//        // If container has binding or can resolve it, use it
//        if ($container->has($class)) {
//            return $container->make($class);
//        }
//
//        // Otherwise fallback to reflection (for unbound concretes)
//        $reflection = new \ReflectionClass($class);
//
//        if (!$reflection->isInstantiable()) {
//            throw new \RuntimeException("Class {$class} is not instantiable");
//        }
//
//        $constructor = $reflection->getConstructor();
//
//        if (!$constructor) {
//            return new $class();
//        }
//
//        $dependencies = [];
//
//        foreach ($constructor->getParameters() as $param) {
//            $type = $param->getType();
//
//            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
//                if ($param->isDefaultValueAvailable()) {
//                    $dependencies[] = $param->getDefaultValue();
//                    continue;
//                }
//
//                throw new \RuntimeException(
//                    "Cannot resolve parameter '{$param->getName()}' in {$class}"
//                );
//            }
//
//            $dependencies[] = self::resolve($type->getName());
//        }
//
//        return $reflection->newInstanceArgs($dependencies);
    }

}
