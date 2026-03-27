<?php

namespace App\Services\Subscriptions\Printing\Driver;

/**
 * Registry of available PrintRunDriver implementations.
 *
 * Populated by the PrintServiceProvider. Drivers are keyed by their name()
 * value, which must match the `driver` column on PrintProcessConfig.
 *
 * Throws \DomainException when an unknown driver name is requested — this
 * is a non-retryable configuration error that the workflow surfaces as a
 * no-data outcome with operator notification.
 */
class PrintDriverRegistry
{
    /** @var array<string, PrintRunDriverInterface> */
    private array $drivers = [];

    public function register(PrintRunDriverInterface $driver): void
    {
        $this->drivers[$driver->name()] = $driver;
    }

    /**
     * @throws \DomainException When no driver is registered under $name.
     */
    public function get(string $name): PrintRunDriverInterface
    {
        if (!isset($this->drivers[$name])) {
            throw new \DomainException(
                "No print driver registered for '{$name}'. "
                . "Available: " . implode(', ', array_keys($this->drivers))
            );
        }

        return $this->drivers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }
}