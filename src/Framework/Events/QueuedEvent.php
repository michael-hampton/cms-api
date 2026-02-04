<?php

namespace App\Framework\Events;

interface QueuedEvent
{
    public function getQueueName(): string;

    public function getDelay(): int;
}