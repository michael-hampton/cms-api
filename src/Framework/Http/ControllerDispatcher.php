<?php

namespace App\Framework\Http;

use App\Framework\Container;
use App\Models\Page;
use App\Services\Url\UrlResolutionResult;
use ReflectionMethod;
use ReflectionParameter;

class ControllerDispatcher
{
    private Container $container;

    public function __construct()
    {
        $this->container = Container::getInstance();
    }


    public function dispatch(string $controllerAction, Page $page, UrlResolutionResult $result)
    {
        [$controllerClass, $method] = $this->parseControllerAction($controllerAction);

        // Resolve controller from container
        $controller = $this->container->make($controllerClass);

        // Check if method exists
        if (!method_exists($controller, $method)) {
            throw new \BadMethodCallException("Method [{$method}] does not exist on controller [{$controllerClass}]");
        }

        if ($page->custom_handler) {
            return $this->call($controller, $method);
        }

        // Call controller method with page and result
        return $controller->{$method}($page, $result);
    }

    private function parseControllerAction(string $controllerAction): array
    {
        if (str_contains($controllerAction, '@')) {
            return explode('@', $controllerAction, 2);
        }

        // If no method specified, default to 'show'
        return [$controllerAction, 'show'];
    }

    public function call($controller, string $method, array $provided = [])
    {
        $reflector = new ReflectionMethod($controller, $method);

        $dependencies = array_map(function (ReflectionParameter $param) use ($provided, $reflector) {
            $type = $param->getType();

            // If class type → resolve from container
            if ($type && !$type->isBuiltin()) {
                return $this->container->make($type->getName());
            }

            // If value was provided manually → use it
            if (array_key_exists($param->getName(), $provided)) {
                return $provided[$param->getName()];
            }

            // If default value exists → use it
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new \Exception("Cannot resolve parameter \${$param->getName()} for {$reflector->getName()}");
        }, $reflector->getParameters());

        return $reflector->invokeArgs($controller, $dependencies);
    }

}