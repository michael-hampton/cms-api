<?php

namespace App\Framework\Http;

use App\Framework\Container;
use App\Framework\Session\Session;
use App\Framework\Support\Cache\Cache;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;
use App\Services\Url\DynamicUrlResolver;
use App\Services\Url\UrlResolutionResult;
use Exception;
use ReflectionFunction;
use ReflectionMethod;

class Router
{
    private array $routes = [];
    private Container $container;
    private array $middleware = [];
    private array $groupStack = [];
    private array $globalMiddleware = [];
    private array $namedRoutes = [];
    private string $lastHttpMethod;
    private string $lastPath;
    private array $paramPatterns = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }
}
