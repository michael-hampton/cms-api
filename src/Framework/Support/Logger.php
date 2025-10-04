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
//        $logPath = self::$logPath ?: 'logs';
//        $filename = $logPath . '/' . self::$defaultChannel . '-' . date('Y-m-d') . '.log';
//
//        $timestamp = date('Y-m-d H:i:s');
//        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
//        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
//
//        file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
    }
}