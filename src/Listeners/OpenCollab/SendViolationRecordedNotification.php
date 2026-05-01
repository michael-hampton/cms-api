<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ViolationRecordedEvent;
use App\Services\BaseUserNotificationListener;

class SendViolationRecordedNotification extends BaseUserNotificationListener
{
    public function handle(ViolationRecordedEvent $event): void
    {
        $this->notify(
            $event->userId,
            'violation_recorded',
            [
                'message' => $event->violation,
            ],
            'contributor.violation_recorded'
        );
    }
}