<?php

namespace App\Framework\Http;

use App\Framework\Container;
use App\Framework\Session\Session;
use App\Framework\Support\Cache;
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
    private array $groupStack = []; // Track nested groups
    private array $globalMiddleware = [];
    private array $namedRoutes = [];
    private string $lastHttpMethod;
    private string $lastPath;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function middleware(array $middleware): self
    {
        $this->globalMiddleware = array_merge($this->globalMiddleware, $middleware);
        return $this;
    }

    /**
     * Create a route group with shared attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        // Push group attributes onto stack
        $this->groupStack[] = $attributes;

        // Execute the callback to register routes
        $callback($this);

        // Pop the group off the stack
        array_pop($this->groupStack);
    }

    /**
     * Get merged attributes from group stack
     */
    private function mergeGroupAttributes(array $new = []): array
    {
        $merged = [
            'prefix' => '',
            'middleware' => [],
        ];

        // Merge all groups in the stack
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $merged['prefix'] .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $merged['middleware'] = array_merge(
                    $merged['middleware'],
                    (array)$group['middleware']
                );
            }
        }

        // Merge with new attributes
        if (isset($new['prefix'])) {
            $merged['prefix'] .= '/' . trim($new['prefix'], '/');
        }
        if (isset($new['middleware'])) {
            $merged['middleware'] = array_merge(
                $merged['middleware'],
                (array)$new['middleware']
            );
        }

        $merged['prefix'] = '/' . trim($merged['prefix'], '/');

        return $merged;
    }

    /**
     * Enhanced GET method - supports both old and new syntax
     */
    public function get(string $path, $handler, ?string $method = null, array $middleware = []): self
    {
        $this->addRoute('GET', $path, $handler, $method, $middleware);

        return $this;
    }

    /**
     * Enhanced POST method - supports both old and new syntax
     */
    public function post(string $path, $handler, ?string $method = null, array $middleware = []): self
    {
        $this->addRoute('POST', $path, $handler, $method, $middleware);

        return $this;
    }

    /**
     * Enhanced PUT method - supports both old and new syntax
     */
    public function put(string $path, $handler, ?string $method = null, array $middleware = []): self
    {
        $this->addRoute('PUT', $path, $handler, $method, $middleware);

        return $this;
    }

    /**
     * Enhanced DELETE method - supports both old and new syntax
     */
    public function delete(string $path, $handler, ?string $method = null, array $middleware = []): self
    {
        $this->addRoute('DELETE', $path, $handler, $method, $middleware);

        return $this;
    }

    /**
     * Add route with flexible handler support and group awareness
     */
    private function addRoute(string $httpMethod, string $path, $handler, ?string $method = null, array $middleware = [], ?string $name = null): void
    {
        // Get group attributes
        $groupAttributes = $this->mergeGroupAttributes();

        // Apply prefix
        $fullPath = $groupAttributes['prefix'] . '/' . ltrim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');

        // Merge middleware
        $allMiddleware = array_merge($groupAttributes['middleware'], $middleware);

        if ($name !== null) {
            $this->namedRoutes[$name] = [
                'path' => $fullPath,
                'method' => $httpMethod
            ];
        }

        if (is_array($handler) && count($handler) === 2) {
            $this->routes[$httpMethod][$fullPath] = [
                'handler' => $handler,
                'middleware' => $allMiddleware
            ];
        } elseif (is_string($handler) && $method !== null) {
            $this->routes[$httpMethod][$fullPath] = [
                'handler' => [$handler, $method],
                'middleware' => $allMiddleware
            ];
        } elseif (is_callable($handler)) {
            $this->routes[$httpMethod][$fullPath] = [
                'handler' => $handler,
                'middleware' => $allMiddleware
            ];
        } elseif (is_string($handler)) {
            $this->routes[$httpMethod][$fullPath] = [
                'handler' => $handler,
                'middleware' => $allMiddleware
            ];
        } else {
            throw new Exception("Invalid route handler format for {$httpMethod} {$fullPath}");
        }

        // Track the last added route
        $this->lastHttpMethod = $httpMethod;
        $this->lastPath = $fullPath;
    }

    public function dispatch(string $method, string $path, $request = null): Response
    {
        // Sort routes to prioritize specific paths over parameterized ones
        $sortedRoutes = $this->getSortedRoutes($method);

        // Handle parameterized routes
        foreach ($sortedRoutes as $routePath => $routeData) {
            if ($this->matchRoute($routePath, $path, $params)) {
                // Extract handler and middleware
                $handler = $routeData['handler'] ?? $routeData;
                $middlewareStack = array_merge($this->globalMiddleware, $routeData['middleware']);

                Session::setPreviousUrl($routePath);

                return $this->runMiddleware($middlewareStack, $request, function ($request) use ($handler, $params) {
                    return $this->callAction($handler, $request, $params);
                });
            }
        }

        return $this->runMiddleware($this->globalMiddleware, $request, function ($request) use ($path, $method) {

            // Check if it's a dynamic url
            $urlResolver = new DynamicUrlResolver(new Cache());
            $urlResult = $urlResolver->resolve($path);

            if (!$urlResult) {
                return $this->show404($method, $path);
            }

            if ($urlResult->isRedirect()) {
                $urlResolver->executeRedirect($urlResult);
                exit;
            }

            $controllerResolver = new ControllerResolver();

            if ($controllerResolver->shouldUseController($urlResult->page)) {
                return $this->dispatchToController($urlResult, $request);
            }

            return $this->show404($method, $path);
        });

        return $this->show404($method, $path);
    }

    /**
     * Get routes sorted by specificity (most specific first)
     */
    private function getSortedRoutes(string $method): array
    {
        if (!isset($this->routes[$method])) {
            return [];
        }

        $routes = $this->routes[$method];

        // Sort routes: static segments > parameters
        uksort($routes, function ($a, $b) {
            $aSegments = explode('/', trim($a, '/'));
            $bSegments = explode('/', trim($b, '/'));

            // Compare segment by segment
            $maxLength = max(count($aSegments), count($bSegments));

            for ($i = 0; $i < $maxLength; $i++) {
                $aSegment = $aSegments[$i] ?? '';
                $bSegment = $bSegments[$i] ?? '';

                $aIsParam = preg_match('/^\{.*\}$/', $aSegment);
                $bIsParam = preg_match('/^\{.*\}$/', $bSegment);

                // Static segments come before parameters
                if (!$aIsParam && $bIsParam) return -1;
                if ($aIsParam && !$bIsParam) return 1;

                // If both are same type, continue to next segment
                if ($aSegment !== $bSegment) {
                    return strcmp($aSegment, $bSegment);
                }
            }

            return 0;
        });

        return $routes;
    }

    private function runMiddleware(array $middleware, Request $request, callable $finalHandler): Response
    {
        $next = $finalHandler;

        // Build middleware stack in reverse order
        foreach (array_reverse($middleware) as $middlewareClass) {
            $next = function ($request) use ($middlewareClass, $next) {
                $middlewareInstance = $this->container->resolve($middlewareClass);
                return $middlewareInstance->handle($request, $next);
            };
        }

        return $next($request);
    }

    private function dispatchToController(UrlResolutionResult $result, ?Request $request = null)
    {
        $request->setAttribute('page', $result->page);
        $controllerResolver = new ControllerResolver();

        $controllerAction = $controllerResolver->resolve($result->page);
        $controllerDispatcher = new ControllerDispatcher();

        return $controllerDispatcher->dispatch(
            $controllerAction,
            $result->page,
            $result,
            $request
        );
    }

    private function show404(string $method, string $path): Response
    {
        return Response::json(['error' => 'Route not found', 'method' => $method, 'path' => $path], 404);
    }

    /**
     * @throws Exception
     */
    protected function callAction($action, Request $request, array $routeParams = [])
    {
        // Handle string actions with different formats
        if (is_string($action)) {
            // Handle ControllerClass/method format (modern)
            if (strpos($action, '/') !== false) {
                [$controller, $method] = explode('/', $action, 2);
                $controllerInstance = $this->container->resolve($controller);
                return $this->callControllerMethod($controllerInstance, $method, $request, $routeParams);
            } // Handle ControllerClass@method format (Laravel legacy)
            elseif (strpos($action, '@') !== false) {
                [$controller, $method] = explode('@', $action);
                $controllerInstance = $this->container->resolve($controller);
                return $this->callControllerMethod($controllerInstance, $method, $request, $routeParams);
            } // Handle invokable controllers (ControllerClass without method)
            else {
                $controllerInstance = $this->container->resolve($action);
                return $this->callInvokableController($controllerInstance, $request);
            }
        }

        // Handle closure actions
        if (is_callable($action)) {
            return $this->callClosure($action, $request);
        }

        // Handle array actions [ControllerClass::class, 'method']
        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;

            $controllerInstance = is_string($controller)
                ? $this->container->resolve($controller)
                : $controller;

            return $this->callControllerMethod($controllerInstance, $method, $request, $routeParams);
        }

        throw new Exception('Invalid route action');
    }

    /**
     * Call a controller method with dependency injection
     */
    protected function callControllerMethod($controller, string $method, Request $request, array $routeParams = []): mixed
    {
        $reflectionMethod = new ReflectionMethod($controller, $method);
        return $this->resolveMethodDependencies($reflectionMethod, $request, $controller, $routeParams);
    }

    /**
     * Call an invokable controller (__invoke method)
     */
    protected function callInvokableController($controller, Request $request): mixed
    {
        if (!method_exists($controller, '__invoke')) {
            throw new Exception('Controller must have __invoke method or specify method name');
        }

        $reflectionMethod = new ReflectionMethod($controller, '__invoke');
        return $this->resolveMethodDependencies($reflectionMethod, $request, $controller);
    }

    /**
     * Call a closure with dependency injection
     */
    protected function callClosure(callable $action, Request $request): mixed
    {
        $reflectionFunction = new ReflectionFunction($action);
        return $this->resolveFunctionDependencies($reflectionFunction, $request);
    }

    /**
     * Resolve function dependencies for closures
     */
    protected function resolveFunctionDependencies(ReflectionFunction $function, Request $request): mixed
    {
        $parameters = $function->getParameters();
        $arguments = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                $arguments[] = $request;
                continue;
            }

            $typeName = $type->getName();

            // Handle FormRequest
            if (class_exists($typeName) && is_subclass_of($typeName, 'App\Framework\Http\FormRequest')) {
                $formRequest = $typeName::createFromRequest($request);
                $arguments[] = $formRequest;
                continue;
            }

            if ($typeName === Request::class || (class_exists($typeName) && is_subclass_of($typeName, Request::class))) {
                $arguments[] = $request;
                continue;
            }

            try {
                $arguments[] = $this->container->resolve($typeName);
            } catch (Exception $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve parameter {$parameter->getName()}: " . $e->getMessage());
                }
            }
        }

        return $function->invokeArgs($arguments);
    }

    public function getRouteParams(string $routeUri, array $matches): array
    {
        $params = [];
        // Extract parameter names from the route URI
        preg_match_all('/\\{([a-zA-Z0-9_]+)\\}/', $routeUri, $paramNames);

        // Remove the full match from the matches array
        array_shift($matches);

        // Combine parameter names with their corresponding values
        if (count($paramNames[1]) === count($matches)) {
            $params = array_combine($paramNames[1], $matches);
        }

        return $params;
    }

    /**
     * Resolve method dependencies with enhanced container integration
     */
    protected function resolveMethodDependencies(ReflectionMethod $method, Request $request, $controller = null, array $routeParams = []): mixed
    {
        $parameters = $method->getParameters();
        $arguments = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            $paramName = $parameter->getName();

            // Check if this parameter name matches a route parameter
            if (isset($routeParams[$paramName])) {
                $arguments[] = $this->castRouteParameter($routeParams[$paramName], $type);
                continue;
            }

            // No type hint - pass the request
            if ($type === null) {
                $arguments[] = $request;
                continue;
            }

            $typeName = $type->getName();

            $request->setRouteParams($routeParams);

            // Handle FormRequest
            if (class_exists($typeName) && is_subclass_of($typeName, 'App\Framework\Http\FormRequest')) {
                $formRequest = $typeName::createFromRequest($request)
                    ->setRouteParams($routeParams);

                $arguments[] = $formRequest;
                continue;
            }

            // Handle regular Request
            if ($typeName === Request::class || (class_exists($typeName) && is_subclass_of($typeName, Request::class))) {
                $arguments[] = $request;
                continue;
            }

            // Handle built-in types with defaults
            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve built-in parameter {$paramName} of type {$typeName} without default value");
                }
                continue;
            }

            // Handle other dependencies via enhanced container
            try {
                $arguments[] = $this->container->resolve($typeName);
            } catch (Exception $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                } elseif ($type->allowsNull()) {
                    $arguments[] = null;
                } else {
                    throw new Exception("Cannot resolve parameter {$paramName} of type {$typeName}: " . $e->getMessage());
                }
            }
        }

        $response = $method->invokeArgs($controller, $arguments);

        if ($response instanceof RedirectResponse) {
            // Send it and stop dispatch
            $response->send();
            return $response;
        }

        return $response;
    }

    /**
     * Cast route parameters to appropriate types
     */
    private function castRouteParameter(string $value, ?\ReflectionType $type)
    {
        if ($type === null || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => explode(',', $value),
            default => $value
        };
    }

    /**
     * Match route with parameters
     */
    private function matchRoute(string $routePath, string $requestPath, &$params = []): bool
    {
        $params = [];

        // Convert route pattern to regex
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $requestPath, $matches)) {
            // Extract parameter names from route
            preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);

            // Map parameter values
            for ($i = 1; $i < count($matches); $i++) {
                $paramName = $paramNames[1][$i - 1];
                $params[$paramName] = $matches[$i];
            }

            return true;
        }

        return false;
    }

    /**
     * Get all routes for debugging
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function name(string $name): self
    {
        if ($this->lastHttpMethod === null || $this->lastPath === null) {
            throw new Exception("No route to name. Call name() immediately after defining a route.");
        }

        $this->namedRoutes[$name] = [
            'path' => $this->lastPath,
            'method' => $this->lastHttpMethod
        ];

        return $this;
    }

    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new Exception("Route '{$name}' not found");
        }

        $path = $this->namedRoutes[$name]['path'];

        // Replace route parameters with actual values
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }

        return $path;
    }

    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }
}