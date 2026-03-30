<?php

namespace App\Framework\Support;

namespace App\Framework\Support;

class Logger
{
    private static $logPath;
    private static $defaultChannel = 'app';

    public function __call($method, $args)
    {
        if (method_exists(static::class, $method)) {
            return static::$method(...$args);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    public static function setLogPath(string $path): void
    {
        self::$logPath = rtrim($path, '/');
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        $logPath = self::$logPath ?: __DIR__ . '/../../logs';
        $filename = $logPath . '/' . self::$defaultChannel . '-' . date('Y-m-d') . '.log';

        if (!is_dir($logPath) && !mkdir($logPath, 0777, true) && !is_dir($logPath)) {
            error_log("Logger error: failed to create log directory {$logPath}");
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;

        @file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
    }
}