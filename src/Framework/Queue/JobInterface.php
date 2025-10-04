<?php

namespace App\Framework\Queue;

use Exception;

interface JobInterface
{
    public function handle(): void;
    public function failed(Exception $exception): void;
}