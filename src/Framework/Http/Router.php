<?php

namespace App\Framework\Http;

use App\Framework\Container;
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

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Enhanced GET method - supports both old and new syntax
     */
    public function get(string $path, $handler, ?string $method = null): void
    {
        $this->addRoute('GET', $path, $handler, $method);
    }

    /**
     * Enhanced POST method - supports both old and new syntax
     */
    public function post(string $path, $handler, ?string $method = null): void
    {
        $this->addRoute('POST', $path, $handler, $method);
    }

    /**
     * Enhanced PUT method - supports both old and new syntax
     */
    public function put(string $path, $handler, ?string $method = null): void
    {
        $this->addRoute('PUT', $path, $handler, $method);
    }

    /**
     * Enhanced DELETE method - supports both old and new syntax
     */
    public function delete(string $path, $handler, ?string $method = null): void
    {
        $this->addRoute('DELETE', $path, $handler, $method);
    }

    /**
     * Add route with flexible handler support
     */
    private function addRoute(string $httpMethod, string $path, $handler, ?string $method = null): void
    {
        if (is_array($handler) && count($handler) === 2) {
            // Laravel-style array: [Controller::class, 'method']
            $this->routes[$httpMethod][$path] = $handler;
        } elseif (is_string($handler) && $method !== null) {
            // Your current style: Controller::class, 'method'
            $this->routes[$httpMethod][$path] = [$handler, $method];
        } elseif (is_callable($handler)) {
            // Closure
            $this->routes[$httpMethod][$path] = $handler;
        } elseif (is_string($handler)) {
            // String with @ or / separator
            if (strpos($handler, '@') !== false || strpos($handler, '/') !== false) {
                $this->routes[$httpMethod][$path] = $handler;
            } else {
                // Invokable controller
                $this->routes[$httpMethod][$path] = $handler;
            }
        } else {
            throw new Exception("Invalid route handler format for {$httpMethod} {$path}");
        }
    }

    public function dispatch(string $method, string $path, $request = null): Response
    {
        // Handle parameterized routes
        foreach ($this->routes[$method] ?? [] as $routePath => $handler) {
            if ($this->matchRoute($routePath, $path, $params)) {
                return $this->callAction($handler, $request, $params);
            }
        }

        //check if its a dynamic url
        $urlResolver = new DynamicUrlResolver(new Cache());

        $urlResult = $urlResolver->resolve($path);

        if (!$urlResult) {
            // No page found, show 404
            return $this->show404();
        }

        if($urlResult->isRedirect()) {
            $urlResolver->executeRedirect($urlResult);
            exit;
        }

        $controllerResolver = new ControllerResolver();

        if ($controllerResolver->shouldUseController($urlResult->page)) {
            return $this->dispatchToController($urlResult);
        }

        return $this->show404();

    }

    private function dispatchToController(UrlResolutionResult $result)
    {
        $controllerResolver = new ControllerResolver();

        $controllerAction = $controllerResolver->resolve($result->page);
        $controllerDispatcher = new ControllerDispatcher();

        return $controllerDispatcher->dispatch(
            $controllerAction,
            $result->page,
            $result
        );
    }

    private function show404(): Response
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
            }
            // Handle ControllerClass@method format (Laravel legacy)
            elseif (strpos($action, '@') !== false) {
                [$controller, $method] = explode('@', $action);
                $controllerInstance = $this->container->resolve($controller);
                return $this->callControllerMethod($controllerInstance, $method, $request, $routeParams);
            }
            // Handle invokable controllers (ControllerClass without method)
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

            die('here10');

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

        return $method->invokeArgs($controller, $arguments);
    }

    /**
     * Cast route parameters to appropriate types
     */
    private function castRouteParameter(string $value, ?\ReflectionType $type)
    {
        if ($type === null || !$type->isBuiltin()) {
            return $value;
        }

        return match($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
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
}