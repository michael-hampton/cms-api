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
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Bind a class or interface to a concrete implementation
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * Register a singleton binding
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as shared
     */
    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve a class from the container
     */
    public function resolve(string $abstract)
    {
        if ($abstract === Database::class) {
            return Database::getInstance();
        }

        // Check if we have a concrete instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check if we're already building this (circular dependency protection)
        if (isset($this->building[$abstract])) {
            throw new \Exception("Circular dependency detected: {$abstract}");
        }

        $this->building[$abstract] = true;

        try {
            $concrete = $this->getConcrete($abstract);

            if ($this->isBuildable($concrete, $abstract)) {
                $object = $this->build($concrete);
            } else {
                $object = $this->resolve($concrete);
            }

            // If this is a singleton, store the instance
            if ($this->isShared($abstract)) {
                $this->instances[$abstract] = $object;
            }

            $this->fireAfterResolvingCallbacks($abstract, $object);

            return $object;
        } finally {
            unset($this->building[$abstract]);
        }
    }

    /**
     * Register a callback to fire after resolving
     */
    public function afterResolving(string $abstract, Closure $callback): void
    {
        $this->afterResolvingCallbacks[$abstract][] = $callback;
    }

    /**
     * Determine if the given abstract is buildable
     */
    protected function isBuildable($concrete, string $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * Get the concrete type for a given abstract
     */
    protected function getConcrete(string $abstract)
    {
        // If we have a binding, return the concrete
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        // Return the abstract as the concrete
        return $abstract;
    }

    /**
     * Determine if the given type is shared
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->instances[$abstract]) ||
            (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared'] === true);
    }

    /**
     * Build an instance of the given type
     */
    protected function build($concrete)
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

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();
        $instances = $this->resolveDependencies($dependencies);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Resolve all dependencies from ReflectionParameters
     */
    protected function resolveDependencies(array $dependencies): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $result = $this->resolveDependency($dependency);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Resolve a single dependency
     */
    protected function resolveDependency(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();
        $paramName = '$' . $parameter->getName();

        // Check contextual binding by parameter name (e.g. '$channelMap')
        if ($this->currentlyBuilding() && $this->hasContextualBinding($this->currentlyBuilding(), $paramName)) {
            return $this->resolveContextualBinding($this->currentlyBuilding(), $paramName);
        }

        // If no type hint, check for default value
        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new \Exception("Cannot resolve dependency [{$parameter->getName()}] without type hint");
        }

        // Handle union types (PHP 8.0+)
        if (method_exists($type, 'getTypes')) {
            // For union types, try to resolve the first non-built-in type
            foreach ($type->getTypes() as $unionType) {
                if (!$unionType->isBuiltin()) {
                    try {
                        return $this->resolve($unionType->getName());
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }

        // Handle nullable types
        if ($type->allowsNull() && $parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $typeName = $type->getName();

        // Can't resolve built-in types automatically
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
        return array_key_last($this->building) ?? null;  // building is already tracked
    }

    private function hasContextualBinding(string $concrete, string $abstract): bool
    {
        return isset($this->contextualBindings[$concrete][$abstract]);
    }

    private function resolveContextualBinding(string $concrete, string $abstract): mixed
    {
        $binding = $this->contextualBindings[$concrete][$abstract];

        return $binding instanceof Closure
            ? $binding($this)
            : $this->make($binding);
    }

    /**
     * Get a closure to resolve the given type from the container
     */
    protected function getClosure(string $abstract, string $concrete): Closure
    {
        return function ($container) use ($abstract, $concrete) {
            if ($abstract == $concrete) {
                return $container->build($concrete);
            }

            return $container->resolve($concrete);
        };
    }

    /**
     * Fire after resolving callbacks
     */
    protected function fireAfterResolvingCallbacks(string $abstract, $object): void
    {
        if (isset($this->afterResolvingCallbacks[$abstract])) {
            foreach ($this->afterResolvingCallbacks[$abstract] as $callback) {
                $callback($object, $this);
            }
        }
    }

    /**
     * Check if the container can resolve the given type
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
            isset($this->instances[$abstract]) ||
            class_exists($abstract) ||
            interface_exists($abstract);
    }

    /**
     * Get all bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Flush the container
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->singletons = [];
        $this->afterResolvingCallbacks = [];
        $this->building = [];
    }

    public function make(string $abstract)
    {
        if ($abstract === Database::class) {
            return Database::getInstance();
        }

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {

            $binding = $this->bindings[$abstract];
            $concrete = $binding['concrete'];

            $object = $concrete instanceof Closure
                ? $concrete($this)
                : $this->build($concrete);

            if ($binding['shared'] ?? false) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        return $this->build($abstract);
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract]);
    }

    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

// 3. Called by the builder
    public function addContextualBinding(string $concrete, string $abstract, Closure|string $implementation): void
    {
        $this->contextualBindings[$concrete][$abstract] = $implementation;
    }
}