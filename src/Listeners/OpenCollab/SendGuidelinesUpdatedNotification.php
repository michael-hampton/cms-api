<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\GuidelinesVersionBumpedEvent;
use App\Services\BaseUserNotificationListener;

class SendGuidelinesUpdatedNotification extends BaseUserNotificationListener
{
    public function handle(GuidelinesVersionBumpedEvent $event): void
    {
        $this->notify(
            $event->userId,
            'guidelines_updated',
            [
                'version' => $event->newVersion,
            ]
        );
    }
}