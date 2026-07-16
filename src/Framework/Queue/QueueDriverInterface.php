<?php

namespace App\Framework\Queue;

interface QueueDriverInterface
{
    public function push(Job $job): int;

    public function pop(): ?QueuedJob;
}