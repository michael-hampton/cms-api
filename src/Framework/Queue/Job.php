<?php

namespace App\Framework\Queue;

use App\Framework\Support\Logger;
use Exception;
abstract class Job implements JobInterface
{
    public $tries = 3;
    public $timeout = 60;
    public $delay = 0;

    public function failed(Exception $exception): void
    {
        Logger::error('Job failed', [
            'job' => get_class($this),
            'error' => $exception->getMessage()
        ]);
    }
}