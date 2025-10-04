<?php

namespace App\config;

use App\Framework\Support\Env;

class DatabaseConfig
{
    public static function getConfig(): array
    {
        return [
            'default' => Env::get('DB_CONNECTION', 'mysql'),

            'connections' => [
                'mysql' => [
                    'host_cli' => Env::get('DB_HOST_CLI', ''),
                    'driver' => 'mysql',
                    'host' => Env::get('DB_HOST', ''),
                    'port' => Env::get('DB_PORT', 3306),
                    'database' => Env::get('DB_DATABASE'),
                    'username' => Env::get('DB_USERNAME'),
                    'password' => Env::get('DB_PASSWORD', ''),
                    'unix_socket' => Env::get('DB_SOCKET', ''),
                    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
                    'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
                    'prefix' => Env::get('DB_PREFIX', ''),
                    'strict' => Env::get('DB_STRICT', true),
                    'engine' => Env::get('DB_ENGINE'),
                ],

                'sqlite' => [
                    'driver' => 'sqlite',
                    'url' => Env::get('DATABASE_URL'),
                    'database' => Env::get('DB_DATABASE', 'database/database.sqlite'),
                    'prefix' => Env::get('DB_PREFIX', ''),
                    'foreign_key_constraints' => Env::get('DB_FOREIGN_KEYS', true),
                ],

                'pgsql' => [
                    'driver' => 'pgsql',
                    'url' => Env::get('DATABASE_URL'),
                    'host' => Env::get('DB_HOST', '127.0.0.1'),
                    'port' => Env::get('DB_PORT', 5432),
                    'database' => Env::get('DB_DATABASE'),
                    'username' => Env::get('DB_USERNAME'),
                    'password' => Env::get('DB_PASSWORD', ''),
                    'charset' => Env::get('DB_CHARSET', 'utf8'),
                    'prefix' => Env::get('DB_PREFIX', ''),
                    'schema' => Env::get('DB_SCHEMA', 'public'),
                    'sslmode' => Env::get('DB_SSLMODE', 'prefer'),
                ],
            ],

            // Database logging settings
            'log_queries' => Env::get('DB_LOG_QUERIES', false),
            'slow_query_threshold' => Env::get('DB_SLOW_QUERY_THRESHOLD', 1000),
        ];
    }
}