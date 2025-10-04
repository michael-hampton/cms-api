<?php

namespace App\Framework\Console;

class Artisan
{
    private $commands = [];

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

        $commandClass = $this->commands[$commandName];
        $command = new $commandClass();

        // Pass arguments to command
        $command->setArguments($arguments);

        return $command->handle();
    }

    private function showHelp(): void
    {
        echo "Available commands:\n";
        foreach ($this->commands as $name => $class) {
            $command = new $class();
            echo "  {$name}\t{$command->description}\n";
        }
    }
}