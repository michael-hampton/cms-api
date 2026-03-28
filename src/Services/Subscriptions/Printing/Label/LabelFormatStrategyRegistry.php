<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Label;

use App\Enums\Subscriptions\LabelExportFormat;

/**
 * Registry of available LabelExportFormatStrategy implementations.
 *
 * Populated by PrintServiceProvider. Keyed by LabelExportFormat value.
 *
 * Throws \DomainException when an unregistered format is requested —
 * this is a non-retryable configuration error.
 */
class LabelFormatStrategyRegistry
{
    /** @var array<string, LabelExportFormatStrategy> */
    private array $strategies = [];

    public function register(LabelExportFormat $format, LabelExportFormatStrategy $strategy): void
    {
        $this->strategies[$format->value] = $strategy;
    }

    /**
     * @throws \DomainException When no strategy is registered for $format.
     */
    public function get(LabelExportFormat $format): LabelExportFormatStrategy
    {
        if (!isset($this->strategies[$format->value])) {
            throw new \DomainException(
                "No label format strategy registered for '{$format->value}'. "
                . "Available: " . implode(', ', array_keys($this->strategies))
            );
        }

        return $this->strategies[$format->value];
    }

    public function has(LabelExportFormat $format): bool
    {
        return isset($this->strategies[$format->value]);
    }
}