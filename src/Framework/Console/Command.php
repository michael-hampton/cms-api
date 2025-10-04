<?php

namespace App\Framework\Console;

abstract class Command
{
    protected $signature;
    protected $description;
    protected $arguments = [];
    protected $options = [];

    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
        $this->parseArguments($arguments);
    }

    protected function parseArguments(array $arguments): void
    {
        foreach ($arguments as $arg) {
            if (strpos($arg, '--') === 0) {
                // Handle options like --class=UserSeeder
                $parts = explode('=', substr($arg, 2), 2);
                $this->options[$parts[0]] = $parts[1] ?? true;
            }
        }
    }

    protected function argument(string $name): ?string
    {
        $index = $this->getArgumentIndex($name);
        return $this->arguments[$index] ?? null;
    }

    protected function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    private function getArgumentIndex(string $name): int
    {
        // Simple mapping - in real implementation, parse signature
        $argumentMap = ['name' => 0];
        return $argumentMap[$name] ?? 0;
    }

    abstract public function handle(): int;

    protected function info(string $message): void
    {
        echo "\033[32m" . $message . "\033[0m" . PHP_EOL;
    }

    protected function error(string $message): void
    {
        echo "\033[31m" . $message . "\033[0m" . PHP_EOL;
    }

    protected function warn(string $message): void
    {
        echo "\033[33m" . $message . "\033[0m" . PHP_EOL;
    }

    protected function ask(string $question): string
    {
        echo $question . ': ';
        return trim(fgets(STDIN));
    }

    protected function confirm(string $question): bool
    {
        echo $question . ' (y/n): ';
        $answer = trim(fgets(STDIN));
        return in_array(strtolower($answer), ['y', 'yes']);
    }
}