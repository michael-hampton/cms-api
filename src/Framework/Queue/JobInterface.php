<?php

namespace App\Framework\Queue;

use Exception;

interface JobInterface
{
    public function handle();
    public function failed(Exception $exception): void;
}