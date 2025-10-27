<?php

namespace App\Framework\Tests\Factories;

trait HasFactories
{
    protected function factory(string $model): Factory
    {
        $factoryClass = $this->resolveFactoryClass($model);

        if (!class_exists($factoryClass)) {
            throw new \InvalidArgumentException("Factory [{$factoryClass}] not found.");
        }

        $factory = new $factoryClass();

        // Auto-inject site_id if available
        if (property_exists($this, 'siteId') && method_exists($factory, 'forSite')) {
            $factory->forSite($this->siteId);
        }

        return $factory;
    }

    protected function resolveFactoryClass(string $model): string
    {
        if (class_exists($model)) {
            $modelName = class_basename($model);
        } else {
            $modelName = $model;
        }

        return "App\\Factories\\{$modelName}Factory";
    }
}