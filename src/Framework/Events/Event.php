<?php

namespace App\Framework\Events;

abstract class Event
{
    public function __construct(
        public readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    )
    {
    }
}