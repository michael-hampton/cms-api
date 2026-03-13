<?php

namespace App\Framework\Console;

class Artisan
{
    private array $commands = [];

    public function register(string $name, string $commandClass): void
    {
        $this->commands[$name] = $commandClass;
    }

    public function run(array $argv): int
    {
        if (count($argv) < 2) {
            $this->showHelp();
            return 0;
        }

        $commandName = $argv[1];
        $arguments = array_slice($argv, 2);

        if (!isset($this->commands[$commandName])) {
            echo "Command '{$commandName}' not found.\n";
            return 1;
        }

        $command = $this->resolve($this->commands[$commandName]);
        $command->setArguments($arguments);

        return $command->handle();
    }

    // ── Resolution ────────────────────────────────────────────────────────

    /**
     * Instantiate a command class by autowiring its constructor parameters
     * through the application container.
     *
     * Falls back to a plain `new $class()` only if the class has no constructor
     * or has no typed parameters, so zero-dependency commands keep working.
     */
    private function resolve(string $class): Command
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                // Resolve class/interface dependencies from the container.
                $args[] = app($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new \RuntimeException(
                "Cannot resolve parameter \${$param->getName()} for command {$class}. "
                . "Only type-hinted class dependencies and parameters with defaults are supported."
            );
        }

        return $reflection->newInstanceArgs($args);
    }

    // ── Help ──────────────────────────────────────────────────────────────

    private function showHelp(): void
    {
        echo "Available commands:\n";
        foreach ($this->commands as $name => $class) {
            try {
                $command = $this->resolve($class);
                $description = $command->description ?? '';
            } catch (\Throwable) {
                // If resolution fails during help, show what we can.
                $description = "(dependencies unavailable)";
            }
            echo "  {$name}\t{$description}\n";
        }
    }
}