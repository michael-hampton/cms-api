<?php

namespace App\Framework;

use App\Framework\Database\Database;

class ModelRegistry
{
    private static $models = [];

    public static function register(string $table, string $modelClass): void
    {
        self::$models[$table] = $modelClass;
    }

    public static function getModelForTable(string $table): ?string
    {
        if(empty(self::$models)) {
            self::autoRegister();
        }

        return self::$models[$table] ?? null;
    }

    public static function autoRegister(?Database $database = null): void
    {
        $models = AutoDiscovery::discoverModels();

        foreach ($models as $modelClass) {
            $instance = new $modelClass([], $database);
            if (method_exists($instance, 'getTable') && !empty($instance->getTable())) {
                self::register($instance->getTable(), $modelClass);
            }
        }
    }
}