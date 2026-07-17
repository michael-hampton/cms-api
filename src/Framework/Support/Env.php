<?php

namespace App\Framework\Support;

use Exception;

class Env
{
    private static $variables = [];
    private static $loaded = false;

    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $path ?: self::findEnvFile();

        if (!$envFile || !file_exists($envFile)) {
            // Don't throw error - just use system env vars
            self::$loaded = true;
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new Exception("Could not read .env file: {$envFile}");
        }

        foreach ($lines as $line) {
            self::parseLine($line);
        }

        self::$loaded = true;
    }

    private static function findEnvFile(): ?string
    {
        $dir = __DIR__;

        while ($dir !== dirname($dir)) {
            $file = $dir . DIRECTORY_SEPARATOR . '.env';

            if (is_file($file)) {
                return $file;
            }

            $dir = dirname($dir);
        }

        return null;
    }

    private static function parseLine(string $line): void
    {
        $line = trim($line);

        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
            return;
        }

        // Handle inline comments
        if (strpos($line, ' #') !== false) {
            $line = substr($line, 0, strpos($line, ' #'));
        }

        // Must contain equals sign
        if (strpos($line, '=') === false) {
            return;
        }

        list($key, $value) = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        // Remove quotes from value
        $value = self::removeQuotes($value);

        // Parse special values
        $value = self::parseValue($value);

        // Store in our variables array
        self::$variables[$key] = $value;

        // Also set in $_ENV and $_SERVER for compatibility
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;

        // Set as environment variable if possible
        if (function_exists('putenv')) {
            putenv("{$key}={$value}");
        }
    }

    private static function removeQuotes(string $value): string
    {
        // Handle single quotes
        if (strlen($value) >= 2 && $value[0] === "'" && $value[-1] === "'") {
            return substr($value, 1, -1);
        }

        // Handle double quotes with escape sequences
        if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
            $value = substr($value, 1, -1);

            // Process escape sequences
            $value = str_replace([
                '\\n', '\\r', '\\t', '\\"', '\\\\'
            ], [
                "\n", "\r", "\t", '"', '\\'
            ], $value);

            return $value;
        }

        return $value;
    }

    private static function parseValue(string $value): mixed
    {
        // Handle boolean values
        $lowerValue = strtolower($value);
        if (in_array($lowerValue, ['true', 'false'])) {
            return $lowerValue === 'true';
        }

        // Handle null
        if (in_array($lowerValue, ['null', '(null)'])) {
            return null;
        }

        // Handle empty string
        if (in_array($lowerValue, ['empty', '(empty)']) || $value === '') {
            return '';
        }

        // Handle numeric values
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        // Handle variable substitution ${VAR} or $VAR
        $value = self::expandVariables($value);

        return $value;
    }

    private static function lookup(string $key, $default = ''): mixed
    {
        // Just return what we already have in memory/env
        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        return $value !== false ? $value : $default;
    }

    private static function expandVariables(string $value, int $depth = 0, array $seen = []): string
    {
        // prevent infinite recursion
        if ($depth > 10) {
            throw new Exception("Too much variable nesting in env value: {$value}");
        }

        // ${VAR_NAME} syntax
        $value = preg_replace_callback('/\$\{([^}]+)\}/', function($matches) use ($depth, $seen) {
            $varName = $matches[1];

            // cycle detection
            if (in_array($varName, $seen, true)) {
                throw new Exception("Cyclic reference detected in env variables: " . implode(' -> ', [...$seen, $varName]));
            }

            $replacement = (string) self::lookup($varName, '');
            return self::expandVariables($replacement, $depth + 1, [...$seen, $varName]);
        }, $value);

        // $VAR_NAME syntax
        $value = preg_replace_callback('/\$([A-Z_][A-Z0-9_]*)/', function($matches) use ($depth, $seen) {
            $varName = $matches[1];

            // cycle detection
            if (in_array($varName, $seen, true)) {
                throw new Exception("Cyclic reference detected in env variables: " . implode(' -> ', [...$seen, $varName]));
            }

            $replacement = (string) self::lookup($varName, '');
            return self::expandVariables($replacement, $depth + 1, [...$seen, $varName]);
        }, $value);

        return $value;
    }

    public static function get(string $key, $default = null): mixed
    {
        self::load();

        // Check our parsed variables first
        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        // Fall back to $_ENV
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        // Fall back to $_SERVER
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        // Fall back to getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    public static function set(string $key, $value): void
    {
        self::$variables[$key] = $value;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;

        if (function_exists('putenv')) {
            putenv("{$key}=" . (is_bool($value) ? ($value ? 'true' : 'false') : $value));
        }
    }

    public static function has(string $key): bool
    {
        self::load();

        return array_key_exists($key, self::$variables) ||
            array_key_exists($key, $_ENV) ||
            array_key_exists($key, $_SERVER) ||
            getenv($key) !== false;
    }

    public static function all(): array
    {
        self::load();
        return array_merge($_SERVER, $_ENV, self::$variables);
    }

    public static function required(array $keys): void
    {
        $missing = [];

        foreach ($keys as $key) {
            if (!self::has($key) || self::get($key) === null || self::get($key) === '') {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new Exception('Missing required environment variables: ' . implode(', ', $missing));
        }
    }

    public static function getOrFail(string $key): mixed
    {
        $value = self::get($key);

        if ($value === null) {
            throw new Exception("Environment variable '{$key}' is not set");
        }

        return $value;
    }

    // For testing - reset the environment
    public static function reset(): void
    {
        self::$variables = [];
        self::$loaded = false;
    }
}