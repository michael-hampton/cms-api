<?php

namespace App\Services\OpenCollab;

class NotificationManager
{
    /** @param NotificationChannelInterface[] $channels */
    public function __construct(
        private readonly array $channels
    )
    {
    }

    public function dispatch(object $event): void
    {
        foreach ($this->channels as $channel) {
            if ($channel->supports($event)) {
                $channel->send($event);
            }
        }
    }
}