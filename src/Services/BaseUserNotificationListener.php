<?php

namespace App\Services;

abstract class BaseUserNotificationListener
{
    public function __construct(
        protected UserNotificationService $service
    )
    {
    }

    protected function notify(
        int    $userId,
        string $type,
        array  $data = []
    ): void
    {
        $user = new \App\Models\User(['id' => $userId]);

        $this->service->notify($user, $type, $data);
    }
}