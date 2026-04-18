<?php

namespace App\Jobs;

use App\Framework\Container;
use App\Framework\Queue\Job;

abstract class BaseJob extends Job
{
    public static function for(...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * Re-inject services after the worker deserialises this job.
     *
     * Reflects the constructor, skips built-in types (the primitives that
     * were serialised), and resolves every class/interface type from the
     * container.  This is identical to what the container does when it builds
     * the job fresh — so the job's handle() sees the same dependencies it
     * would in a brand-new process.
     */
    public function __wakeup(): void
    {
        $container = Container::getInstance();

        try {
            $reflection = new \ReflectionObject($this);
        } catch (\ReflectionException) {
            return;
        }

        foreach ($reflection->getProperties() as $property) {
            $type = $property->getType();

            // Skip scalars, arrays, and untyped params — they were serialised.
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            // Services are rehydrated post-construction; readonly props cannot be assigned here.
            if ($property->isReadOnly()) {
                continue;
            }

            $typeName = $type->getName();

            // Skip if the container can't resolve it (interface not bound, etc.)
            if (!$container->bound($typeName)) {
                continue;
            }

            try {
                $property->setAccessible(true);

                if ($property->isInitialized($this)) {
                    $currentValue = $property->getValue($this);
                    if ($currentValue !== null) {
                        continue;
                    }
                }

                $property->setValue($this, $container->resolve($typeName));
            } catch (\Throwable) {
                // Non-fatal: if a service can't be re-injected the job will
                // fail naturally in handle() with a clear property error
                // rather than a cryptic wakeup exception.
                // no-op
            }
        }
    }

    protected function resolveProperty(string $property, string $type): mixed
    {
        try {
            $reflection = new \ReflectionObject($this);
            $refProperty = $reflection->getProperty($property);
            $refProperty->setAccessible(true);

            if ($refProperty->isInitialized($this)) {
                $value = $refProperty->getValue($this);

                if ($value !== null) {
                    return $value;
                }
            }

            $value = Container::getInstance()->resolve($type);
            $refProperty->setValue($this, $value);

            return $value;
        } catch (\ReflectionException) {
            // Fall back to a direct resolve if the property is missing.
            // This keeps the helper safe for incremental adoption.
            return Container::getInstance()->resolve($type);
        }
    }

}
