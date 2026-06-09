<?php

namespace App\Tests\Support;

trait CapturesConsoleOutput
{
    protected function captureOutput(callable $callback): string
    {
        ob_start();

        try {
            $callback();
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}