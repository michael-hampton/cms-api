<?php

namespace App\Framework\Support;

class Logger
{
    private static $logPath;
    private static $defaultChannel = 'app';

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
        $logPath = self::$logPath ?: __DIR__ . '/../../logs'; // absolute path is safer
        $filename = $logPath . '/' . self::$defaultChannel . '-' . date('Y-m-d') . '.log';

        // Ensure log directory exists
        if (!is_dir($logPath) && !mkdir($logPath, 0777, true) && !is_dir($logPath)) {
            // Failed to create directory — fallback to stderr to avoid warnings
            error_log("Logger error: failed to create log directory {$logPath}");
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;

        // Write safely, suppress any PHP warning if file is temporarily unavailable
        @file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
    }

}