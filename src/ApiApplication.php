<?php

namespace App;

use App\Events\DatabaseEventSubscriber;
use App\Framework\Console\Artisan;
use App\Framework\Console\Commands\MakeControllerCommand;
use App\Framework\Console\Commands\MakeMigrationCommand;
use App\Framework\Console\Commands\MakeModelCommand;
use App\Framework\Console\Commands\MakeRepositoryCommand;
use App\Framework\Console\Commands\MigrateCommand;
use App\Framework\Console\Commands\MigrateRollbackCommand;
use App\Framework\Console\Commands\QueueWorkCommand;
use App\Framework\Console\Commands\ScheduleRunCommand;
use App\Framework\Console\Commands\SeedCommand;
use App\Framework\Database\Database;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Http\Router;
use App\Framework\Middleware\SessionMiddleware;
use App\Framework\Routing\RouteLoader;
use App\Models\Block;
use App\Models\Page;
use App\Observers\BlockObserver;
use App\Observers\PageObserver;
use Exception;

require_once __DIR__ . '/bootstrap.php';

class ApiApplication
{
    private $container;
    private $router;
    private RouteLoader $routeLoader;

    public function __construct(array $databaseConfig = [], ?Database $database = null)
    {
        // Bootstrap the application with enhanced container
        $this->container = bootstrapApplication($databaseConfig, $database);

        // Create router and register it as singleton in container
        $this->router = new Router($this->container);

        $this->registerMiddleware();

        $this->container->instance(Router::class, $this->router);

        // Create route loader using container
        $this->routeLoader = $this->container->resolve(RouteLoader::class);

        // Load routes from separate files
        $this->loadRoutes();

        // Setup other services
        $this->setupArtisan();
        $this->registerObservers();
    }

    private function registerMiddleware()
    {
        $this->router->middleware([
            SessionMiddleware::class
        ]);
    }

    /**
     * Load routes from separate files
     */
    private function loadRoutes(): void
    {
        $routeFiles = [
            'routes/api.php',
            'routes/web.php',
        ];

        foreach ($routeFiles as $routeFile) {
            if (file_exists($routeFile)) {
                $this->routeLoader->load($routeFile);
            }
        }
    }

    /**
     * Register model observers
     */
    private function registerObservers(): void
    {
        // These could also be moved to a service provider
        Page::observe($this->container->resolve(PageObserver::class));
        Block::observe($this->container->resolve(BlockObserver::class));

        // Subscribe to global database events
        DatabaseEventSubscriber::subscribe();
    }

    /**
     * Setup Artisan console commands
     */
    public function setupArtisan(): Artisan
    {
        $artisan = $this->container->resolve(Artisan::class);

        // Register commands - these could also be auto-discovered
        $commands = [
            'migrate' => MigrateCommand::class,
            'migrate:rollback' => MigrateRollbackCommand::class,
            'make:migration' => MakeMigrationCommand::class,
            'make:controller' => MakeControllerCommand::class,
            'make:model' => MakeModelCommand::class,
            'make:repository' => MakeRepositoryCommand::class,
            'db:seed' => SeedCommand::class,
            'schedule:run' => ScheduleRunCommand::class,
            'queue:work' => QueueWorkCommand::class,
        ];

        foreach ($commands as $name => $commandClass) {
            $artisan->register($name, $commandClass);
        }

        return $artisan;
    }

    /**
     * Handle HTTP request
     */
    public function handleRequest(string $method, string $path, array $data = []): Response
    {
        try {
            $request = $this->container->resolve(Request::class);
            return $this->router->dispatch($method, $path, $request);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle exceptions with proper error responses
     */
    private function handleException(Exception $e): Response
    {
        $data = [
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'status' => 500,
            'timestamp' => date('c')
        ];

        echo $e->getMessage();
        die('here error');

        // In development, include stack trace
        if (config('app.debug', false)) {
            $data['trace'] = $e->getTraceAsString();
        }

        return Response::json($data, 500);
    }

    /**
     * Get the container instance
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get the router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }
}