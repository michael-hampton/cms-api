<?php

namespace App\Parsers\Dtos;

abstract class BaseBlockDto implements BlockDtoInterface
{
    /**
     * Debug mode - logs warnings for unknown fields
     */
    protected static bool $debugMode = true;

    /**
     * Validate that all array keys map to DTO properties
     */
    protected static function validateKeys(array $data, array $knownKeys): void
    {
        if (!self::$debugMode) {
            return;
        }

        $unknownKeys = array_diff(array_keys($data), $knownKeys);

        if (!empty($unknownKeys)) {
            $className = static::class;
            $unknownList = implode(', ', $unknownKeys);
            error_log("WARNING: DTO {$className} received unknown fields: {$unknownList}");
            echo "WARNING: DTO {$className} received unknown fields: {$unknownList}";
            die;
        }
    }

    /**
     * Apply safe defaults for common fields
     */
    protected static function applyDefaults(array $data, array $defaults): array
    {
        return array_merge($defaults, $data);
    }

    /**
     * Validate enum value
     */
    protected static function validateEnum(string $value, array $allowed, string $default, string $fieldName): string
    {
        if (!in_array($value, $allowed, true)) {
            if (self::$debugMode) {
                error_log("WARNING: Invalid {$fieldName} '{$value}', using default '{$default}'");
            }
            return $default;
        }
        return $value;
    }

    /**
     * Validate integer range
     */
    protected static function validateRange(int $value, int $min, int $max, string $fieldName): int
    {
        if ($value < $min || $value > $max) {
            $clamped = max($min, min($max, $value));
            if (self::$debugMode) {
                error_log("WARNING: {$fieldName} value {$value} outside range [{$min}, {$max}], clamped to {$clamped}");
            }
            return $clamped;
        }
        return $value;
    }
}