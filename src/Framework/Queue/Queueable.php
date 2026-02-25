<?php

namespace App\Framework\Queue;

trait Queueable
{
    public ?string $connection = null;
    public ?string $queue = null;
    public int $delay = 0;
    public int $tries = 1;
    public int $timeout = 60;

    public function onConnection(string $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function delay(int $seconds): static
    {
        $this->delay = $seconds;
        return $this;
    }
}