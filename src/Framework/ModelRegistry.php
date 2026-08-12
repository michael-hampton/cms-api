<?php

namespace App\Framework;

use App\Framework\Database\Database;
use App\Models\User;

class ModelRegistry
{
    private static $models = [];

    public static function register(string $table, string $modelClass): void
    {
        self::$models[$table] = $modelClass;
    }

    public static function getModelForTable(string $table): ?string
    {
        $table = trim($table, '`'); // Clean the input string

        if(empty(self::$models)) {
            self::autoRegister();
        }

        return self::$models[$table] ?? null;
    }

    public static function autoRegister(?Database $database = null): void
    {
        // Table→model mapping is process-static metadata. Re-discovering and
        // instantiating every model on each ApiApplication boot is expensive
        // in the functional suite and adds no isolation value.
        if (!empty(self::$models)) {
            return;
        }

        $models = AutoDiscovery::discoverModels();

        foreach ($models as $modelClass) {
            if($modelClass === User::class) {
                continue;
            }

            $instance = new $modelClass([], $database);
            if (method_exists($instance, 'getTable') && !empty($instance->getTable())) {
                self::register($instance->getTable(), $modelClass);
            }
        }
    }

    public static function flush(): void
    {
        self::$models = [];
    }
}