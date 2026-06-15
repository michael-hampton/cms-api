<?php

namespace App\Framework\Http;

use App\Framework\Container;
use App\Middleware\PublicContent\PublicContentRolloutMiddleware;
use App\Models\Page;
use App\Services\Url\UrlResolutionResult;
use ReflectionMethod;
use ReflectionParameter;

class ControllerDispatcher
{
    private Container $container;

    /** @var list<class-string> */
    private array $middleware = [
        PublicContentRolloutMiddleware::class,
    ];

    public function __construct()
    {
        $this->container = Container::getInstance();
    }

    public function dispatch(string $controllerAction, Page $page, UrlResolutionResult $result, Request $request)
    {
        [$controllerClass, $method] = $this->parseControllerAction($controllerAction);

        $request->setAttribute('page', $page);
        $request->setAttribute('url_resolution', $result);
        $request->setAttribute('controller_action', $controllerAction);

        $controller = $this->container->make($controllerClass);

        if (!method_exists($controller, $method)) {
            throw new \BadMethodCallException("Method [{$method}] does not exist on controller [{$controllerClass}]");
        }

        $next = function (Request $request) use ($controller, $method, $page, $result) {
            if ($page->custom_handler) {
                return $this->call($controller, $method);
            }

            return $controller->{$method}($page, $result);
        };

        foreach (array_reverse($this->middleware) as $middlewareClass) {
            $next = function (Request $request) use ($middlewareClass, $next) {
                $middleware = $this->container->resolve($middlewareClass);
                return $middleware->handle($request, $next);
            };
        }

        return $next($request);
    }

    private function parseControllerAction(string $controllerAction): array
    {
        if (str_contains($controllerAction, '@')) {
            return explode('@', $controllerAction, 2);
        }

        return [$controllerAction, 'show'];
    }

    public function call($controller, string $method, array $provided = [])
    {
        $reflector = new ReflectionMethod($controller, $method);

        $dependencies = array_map(function (ReflectionParameter $param) use ($provided, $reflector) {
            $type = $param->getType();

            if ($type && !$type->isBuiltin()) {
                return $this->container->make($type->getName());
            }

            if (array_key_exists($param->getName(), $provided)) {
                return $provided[$param->getName()];
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new \Exception("Cannot resolve parameter \${$param->getName()} for {$reflector->getName()}");
        }, $reflector->getParameters());

        return $reflector->invokeArgs($controller, $dependencies);
    }
}
