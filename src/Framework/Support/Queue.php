<?php

namespace App\Framework\Support;

use Exception;

class Queue
{
    private static $jobs = [];

    public static function push(callable $job, array $data = []): void
    {
        self::$jobs[] = ['job' => $job, 'data' => $data];
    }

    public static function work(): void
    {
        echo '<pre>';
        print_r(self::$jobs);
        die;

        while (!empty(self::$jobs)) {
            $item = array_shift(self::$jobs);

            try {
                $item['job']($item['data']);
            } catch (Exception $e) {
                Logger::error('Queue job failed: ' . $e->getMessage());
            }
        }
    }
}