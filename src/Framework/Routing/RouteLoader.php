<?php

namespace App\Framework\Routing;

use App\Framework\Http\Router;

class RouteLoader
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Load routes from a file
     */
    public function load(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Route file not found: {$filePath}");
        }

        // Make router available in route file scope
        $router = $this->router;

        // Load the route file
        require $filePath;
    }

    /**
     * Load routes from multiple files
     */
    public function loadMultiple(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
            $this->load($filePath);
        }

        $globalAuthRoutes = dirname($filePaths[0] ?? '') . '/global-auth.php';

        if ($globalAuthRoutes !== '/global-auth.php' && file_exists($globalAuthRoutes)) {
            $this->load($globalAuthRoutes);
        }
    }

    /**
     * Load routes with middleware group
     */
    public function group(array $attributes, string $filePath): void
    {
        // Store current group attributes
        $previousGroup = $this->router->getCurrentGroup();

        // Set new group
        $this->router->group($attributes);

        // Load routes
        $this->load($filePath);

        // Restore previous group
        $this->router->setCurrentGroup($previousGroup);
    }
}
