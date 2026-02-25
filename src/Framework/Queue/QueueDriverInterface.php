<?php

namespace App\Framework\Queue;

interface QueueDriverInterface
{
    public function push(Job $job): void;

    public function pop(): ?QueuedJob;
}