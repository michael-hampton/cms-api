<?php

namespace App\Framework;

use App\Framework\Database\Database;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

class Container
{
    private static ?Container $instance = null;

    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];
    private array $afterResolvingCallbacks = [];
    private array $building = [];
    private array $contextualBindings = [];

    public static function getInstance(): Container
    {
        return self::$instance ??= new self();
    }

    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $concrete ??= $abstract;

        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function resolve(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if ($abstract === Database::class) {
            return Database::getInstance();
        }

        if (isset($this->building[$abstract])) {
            throw new \Exception("Circular dependency detected: {$abstract}");
        }

        $this->building[$abstract] = true;

        try {
            $concrete = $this->getConcrete($abstract);
            $object = $this->isBuildable($concrete, $abstract)
                ? $this->build($concrete)
                : $this->resolve($concrete);

            if ($this->isShared($abstract)) {
                $this->instances[$abstract] = $object;
            }

            $this->fireAfterResolvingCallbacks($abstract, $object);

            return $object;
        } finally {
            unset($this->building[$abstract]);
        }
    }

    public function afterResolving(string $abstract, Closure $callback): void
    {
        $this->afterResolvingCallbacks[$abstract][] = $callback;
    }

    protected function isBuildable(mixed $concrete, string $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    protected function getConcrete(string $abstract): mixed
    {
        return $this->bindings[$abstract]['concrete'] ?? $abstract;
    }

    protected function isShared(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared'] === true);
    }

    protected function build(mixed $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new \Exception("Target class [{$concrete}] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Target [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        return $reflector->newInstanceArgs(
            $this->resolveDependencies($constructor->getParameters())
        );
    }

    protected function resolveDependencies(array $dependencies): array
    {
        return array_map(
            fn (ReflectionParameter $dependency): mixed => $this->resolveDependency($dependency),
            $dependencies,
        );
    }

    protected function resolveDependency(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        $paramName = '$' . $parameter->getName();

        if ($this->currentlyBuilding() && $this->hasContextualBinding($this->currentlyBuilding(), $paramName)) {
            return $this->resolveContextualBinding($this->currentlyBuilding(), $paramName);
        }

        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new \Exception("Cannot resolve dependency [{$parameter->getName()}] without type hint");
        }

        if (method_exists($type, 'getTypes')) {
            foreach ($type->getTypes() as $unionType) {
                if (!$unionType->isBuiltin()) {
                    try {
                        return $this->resolve($unionType->getName());
                    } catch (\Exception) {
                        continue;
                    }
                }
            }
        }

        if ($type->allowsNull() && $parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new \Exception("Cannot resolve built-in type [{$typeName}] for parameter [{$parameter->getName()}]");
        }

        return $this->resolve($typeName);
    }

    private function currentlyBuilding(): ?string
    {
        return array_key_last($this->building) ?: null;
    }

    private function hasContextualBinding(string $concrete, string $abstract): bool
    {
        return isset($this->contextualBindings[$concrete][$abstract]);
    }

    private function resolveContextualBinding(string $concrete, string $abstract): mixed
    {
        $binding = $this->contextualBindings[$concrete][$abstract];

        return $binding instanceof Closure ? $binding($this) : $this->make($binding);
    }

    protected function getClosure(string $abstract, string $concrete): Closure
    {
        return function (Container $container) use ($abstract, $concrete): mixed {
            return $abstract === $concrete
                ? $container->build($concrete)
                : $container->resolve($concrete);
        };
    }

    protected function fireAfterResolvingCallbacks(string $abstract, mixed $object): void
    {
        foreach ($this->afterResolvingCallbacks[$abstract] ?? [] as $callback) {
            $callback($object, $this);
        }
    }

    public function make(string $abstract): mixed
    {
        return $this->resolve($abstract);
    }

    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    public function has(string $abstract): bool
    {
        return $this->bound($abstract);
    }

    public function forget(string $abstract): void
    {
        unset($this->bindings[$abstract], $this->instances[$abstract], $this->singletons[$abstract]);
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->singletons = [];
        $this->afterResolvingCallbacks = [];
    }

    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    public function addContextualBinding(string $concrete, string $abstract, mixed $implementation): void
    {
        $this->contextualBindings[$concrete][$abstract] = $implementation;
    }
}
