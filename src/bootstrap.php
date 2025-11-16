<?php

use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Migration\MigrationRunner;
use App\Framework\ModelRegistry;
use App\Framework\ServiceProvider\ServiceProviderManager;
use App\Framework\Support\Config;
use App\Framework\Support\Logger;

function bootstrapApplication(array $databaseConfig, ?Database $database = null): Container
{
    // Set up logging
    Logger::setLogPath('storage/logs');

    $container = Container::getInstance();

    if (!empty($databaseConfig)) {
        Config::set('DatabaseConfig', $databaseConfig);
    }

    if (!$database) {

        // Initialize database early as many services depend on it
        $database = Database::getInstance($databaseConfig);

        // Run migrations
        $migrationRunner = new MigrationRunner($database, 'migrations');
        $migrationRunner->run();
    }

    $container->instance(Database::class, $database);

    // Auto-register models for easier repository resolution
    ModelRegistry::autoRegister($database);

    // Load application configuration
    $config = require 'config/app.php';

    // Initialize service provider manager
    $providerManager = new ServiceProviderManager($container, $config);

    // Register all configured service providers
    foreach ($config['providers'] as $providerClass) {
        $providerManager->register($providerClass);
    }

    // Load environment variables first
    Config::load();

    // Load all configuration files
    loadConfigFiles();

    // Boot all providers
    $providerManager->bootAll();

    return $container;
}

/**
 * Load all configuration files from the config directory
 */
function loadConfigFiles(): void
{
    $configPath = __DIR__ . '/config';

    if (!is_dir($configPath)) {
        return;
    }

    $files = glob($configPath . '/*.php');

    foreach ($files as $file) {
        $key = basename($file, '.php');

        if (Config::has($key)) {
            continue;
        }

        $config = require $file;

        if (is_array($config)) {
            Config::set($key, $config);
        }
    }
}
