<?php

use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Migration\MigrationRunner;
use App\Framework\ModelRegistry;
use App\Framework\ServiceProvider\ServiceProviderManager;
use App\Framework\Support\Logger;

function bootstrapApplication(array $databaseConfig, ?Database $database = null): Container
{
    // Set up logging
    Logger::setLogPath('storage/logs');

    $container = Container::getInstance();

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

    // Boot all providers
    $providerManager->bootAll();

    return $container;
}
